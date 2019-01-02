<?php

namespace GrowthExperiments\HelpPanel;

use CommentStoreComment;
use Config;
use Content;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\Util;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MWException;
use Parser;
use Status;
use Title;
use WikiPage;
use WikitextContent;

class QuestionPoster {

	/**
	 * @var IContextSource
	 */
	private $context;

	/**
	 * @var bool
	 */
	private $isFirstEdit;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var Title
	 */
	private $helpDeskTitle;

	/**
	 * @var string
	 */
	private $resultUrl;

	/**
	 * @var PageUpdater
	 */
	private $pageUpdater;

	/**
	 * @var int
	 */
	private $revisionId;

	/**
	 * @var Parser
	 */
	private $parser;

	/**
	 * @var string
	 */
	private $sectionHeader;

	/**
	 * @var string
	 */
	private $sectionHeaderUnique;

	/**
	 * QuestionPoster constructor.
	 * @param IContextSource $context
	 * @throws \ConfigException
	 */
	public function __construct( IContextSource $context ) {
		$this->context = $context;
		$this->config = $context->getConfig();
		$this->isFirstEdit = ( $context->getUser()->getEditCount() === 0 );
		$this->helpDeskTitle = Title::newFromText( $this->config->get( 'GEHelpPanelHelpDeskTitle' ) );
		$page = new WikiPage( $this->helpDeskTitle );
		$this->pageUpdater = $page->newPageUpdater( $this->context->getUser() );
		$this->parser = MediaWikiServices::getInstance()->getParser();
	}

	/**
	 * @param string $body
	 * @param string $relevantTitle
	 * @return Status
	 * @throws MWException
	 */
	public function submit( $body, $relevantTitle = '' ) {
		$this->setSectionHeader( $relevantTitle );
		$this->pageUpdater->addTag( HelpPanel::HELP_PANEL_QUESTION_TAG );
		$this->pageUpdater->setContent( SlotRecord::MAIN, $this->getContent( $body ) );
		$newRev = $this->pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( $this->getSectionHeader() ),
			EDIT_UPDATE
		);
		if ( !$this->pageUpdater->getStatus()->isGood() ) {
			return $this->pageUpdater->getStatus();
		}
		$this->revisionId = $newRev->getId();
		$this->setResultUrl();
		return Status::newGood();
	}

	/**
	 * @param string $body
	 * @return Content|string|null
	 * @throws MWException
	 */
	public function getContent( $body ) {
		$body = $this->addSignature( $body );
		$parent = $this->pageUpdater->grabParentRevision();
		return $parent->getContent( SlotRecord::MAIN )->replaceSection(
			'new',
			new WikitextContent( $body ),
			$this->getSectionHeader( true )
		);
	}

	/**
	 * Add signature unless already set.
	 *
	 * @param string $body
	 * @return string
	 */
	public function addSignature( $body ) {
		if ( strpos( $body, '~~~~' ) === false ) {
			$body .= "\n\n~~~~";
		}
		return $body;
	}

	/**
	 * @param string $name
	 *   The wiki title that the user opted to include with their question.
	 * @return Status
	 */
	public function validateRelevantTitle( $name ) {
		$title = Title::newFromText( $name );
		return $title && $title->isValid() ?
			Status::newGood() :
			Status::newFatal( 'growthexperiments-help-panel-questionposter-invalid-title' );
	}

	/**
	 * @return string
	 */
	public function getResultUrl() {
		return $this->resultUrl;
	}

	/**
	 * @return int
	 */
	public function getRevisionId() {
		return $this->revisionId;
	}

	/**
	 * @return bool
	 */
	public function isFirstEdit() {
		return $this->isFirstEdit;
	}

	/**
	 * Get the section header for the question posted by the user.
	 *
	 * This method is used for generating the comment summary as well as the
	 * section header in the edit.
	 *
	 * @param bool $withTimestamp
	 * @return string
	 */
	public function getSectionHeader( $withTimestamp = false ) {
		return $withTimestamp ? $this->sectionHeaderUnique : $this->sectionHeader;
	}

	/**
	 * Process potential changes to user's email address.
	 *
	 * The client sends over the email address from the user, which can be one of:
	 *   1. User's confirmed email address
	 *   2. User's unconfirmed email address (unchanged)
	 *   3. New email address supplied via the help panel
	 *   4. Empty string, indicating user's email should be set to empty string.
	 *
	 * @param string $newEmail
	 * @return Status
	 */
	public function handleEmail( $newEmail ) {
		$user = $this->context->getUser();
		$existingEmail = $user->getEmail();
		// If existing email matches the new one, return early if user's email is already
		// confirmed. If their email isn't confirmed, we need to resend the confirmation mail.
		if ( $existingEmail === $newEmail ) {
			if ( $user->isEmailConfirmed() ) {
				return Status::newGood( 'already_confirmed' );
			}
			// Existing email is blank, and new email is blank.
			if ( !$newEmail ) {
				return Status::newGood( 'no_op' );
			}
			// Unconfirmed email: resend the confirmation link.
			$sendConfirmStatus = $user->sendConfirmationMail( 'set' );
			if ( $sendConfirmStatus->isGood() ) {
				// Make the result readable in the API response; default value
				// is null for success.
				$sendConfirmStatus->setResult( true, 'send_confirm' );
			}
			return $sendConfirmStatus;
		}
		// The emails don't match, check if user can change their email.
		if ( !Util::canSetEmail( $user, $newEmail, (bool)$existingEmail ) ) {
			return Status::newFatal( 'growthexperiments-help-panel-questionposter-user-cannot-set-email' );
		}
		// User is blanking the email address, just unset it and don't attempt confirmation.
		if ( !$newEmail ) {
			$user->setEmail( '' );
			$user->saveSettings();
			return Status::newGood( 'unset_email' );
		}
		// New email doesn't match existing email: set and send confirmation.
		$status = $user->setEmailWithConfirmation( $newEmail );
		if ( $status->isGood() ) {
			$status = Status::newGood( 'set_email_with_confirmation' );
		}
		$user->saveSettings();
		return $status;
	}

	/**
	 * Set the result URL with the fragment of the newly created question.
	 */
	public function setResultUrl() {
		$this->helpDeskTitle->setFragment(
			$this->parser->guessSectionNameFromWikiText( $this->getSectionHeader( true ) )
		);
		$this->resultUrl = $this->helpDeskTitle->getFullURL();
	}

	/**
	 * @param string $relevantTitle
	 */
	public function setSectionHeader( $relevantTitle ) {
		$this->sectionHeader = $relevantTitle ?
			$this->context->msg( 'growthexperiments-help-panel-question-subject-template-with-title',
				$relevantTitle )->text() :
			$this->context->msg( 'growthexperiments-help-panel-question-subject-template' )
				->text();
		$lang = MediaWikiServices::getInstance()->getContentLanguage();
		$timestamp = $lang->timeanddate( wfTimestampNow(), false, false );
		$this->sectionHeaderUnique = $this->sectionHeader . ' ' . $this->context->msg( 'parentheses' )->
			rawParams( $timestamp );
	}
}
