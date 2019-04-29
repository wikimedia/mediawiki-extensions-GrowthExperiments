<?php

namespace GrowthExperiments\HelpPanel;

use CommentStoreComment;
use Config;
use Content;
use DerivativeContext;
use FatalError;
use GrowthExperiments\Util;
use Hooks;
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

abstract class QuestionPoster {

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
	private $targetTitle;

	/**
	 * @var string
	 */
	private $resultUrl;

	/**
	 * @var PageUpdater
	 */
	protected $pageUpdater;

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
	protected $relevantTitle;
	/**
	 * @var string
	 */
	private $body;

	/**
	 * QuestionPoster constructor.
	 * @param IContextSource $context
	 * @param string $relevantTitle
	 * @throws MWException
	 */
	public function __construct( IContextSource $context, $relevantTitle = '' ) {
		$this->context = $context;
		$this->relevantTitle = $relevantTitle;
		if ( $this->getContext()->getUser()->isAnon() ) {
			throw new MWException( 'User must be logged-in.' );
		}
		$this->config = $this->getContext()->getConfig();
		$this->isFirstEdit = ( $this->getContext()->getUser()->getEditCount() === 0 );
		$this->targetTitle = $this->getTargetTitle();
		$page = new WikiPage( $this->targetTitle );
		$this->pageUpdater = $page->newPageUpdater( $this->getContext()->getUser() );
		$this->parser = MediaWikiServices::getInstance()->getParser();
	}

	/**
	 * @param string $body
	 * @return Status
	 * @throws MWException
	 * @throws \Exception
	 */
	public function submit( $body ) {
		$userPermissionStatus = $this->checkUserPermissions();
		if ( !$userPermissionStatus->isGood() ) {
			return $userPermissionStatus;
		}
		$content = $this->getContent( $body );
		$editFilterMergedContentHookStatus = $this->runEditFilterMergedContentHook(
			$content,
			$this->getSectionHeader()
		);
		if ( !$editFilterMergedContentHookStatus->isGood() ) {
			return $editFilterMergedContentHookStatus;
		}

		$this->pageUpdater->addTag( $this->getTag() );
		$this->pageUpdater->setContent( SlotRecord::MAIN, $content );
		$newRev = $this->pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( $this->getSectionHeader() )
		);
		if ( !$this->pageUpdater->getStatus()->isGood() ) {
			return $this->pageUpdater->getStatus();
		}
		$this->revisionId = $newRev->getId();
		$this->setResultUrl();
		$question = new QuestionRecord(
			$body,
			$this->getSectionHeaderWithTimestamp(),
			$this->revisionId,
			wfTimestamp(),
			$this->getResultUrl()
		);
		QuestionStoreFactory::newFromUserAndStorage(
			$this->getContext()->getUser(),
			$this->getQuestionStoragePref()
		)->add( $question );
		return Status::newGood();
	}

	/**
	 * The tag to add to the edit via PageUpdater.
	 */
	abstract protected function getTag();

	/**
	 * @param string $body
	 * @return Content|string|null
	 * @throws MWException
	 */
	public function getContent( $body ) {
		$body = $this->addSignature( $body );
		$content = new WikitextContent( $body );
		$header = $this->getSectionHeaderWithTimestamp();
		$parent = $this->pageUpdater->grabParentRevision();
		if ( $parent ) {
			return $parent->getContent( SlotRecord::MAIN )->replaceSection(
				'new',
				$content,
				$header
			);
		}
		return $content->addSectionHeader( $header );
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
	 * @return Status
	 */
	public function validateRelevantTitle() {
		$title = Title::newFromText( $this->relevantTitle );
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
	 * @return string
	 */
	abstract protected function getSectionHeader();

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
		$user = $this->getContext()->getUser()->getInstanceForUpdate();
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
		$this->targetTitle->setFragment(
			$this->parser->guessSectionNameFromWikiText( $this->getSectionHeaderWithTimestamp() )
		);
		$this->resultUrl = $this->targetTitle->getFullURL();
	}

	/**
	 * Get the section header with a timestamp appended.
	 *
	 * @return string
	 */
	protected function getSectionHeaderWithTimestamp() {
		$lang = MediaWikiServices::getInstance()->getContentLanguage();
		$timestamp = $lang->timeanddate(
			wfTimestampNow(),
			// apply time zone adjustment
			/* $adj = */ true,
			// use site default format, not user's chosen format
			/* $format = */ false,
			// use site default time zone, not user's chosen time zone
			// (oddly, empty string is the magic incantation to use the site default)
			/* $timecorrection= */ ''
		);
		return $this->getSectionHeader() . ' ' .
			$this->getContext()->msg( 'parentheses' )
				->plaintextParams( $timestamp )
				->inContentLanguage()->escaped();
	}

	/**
	 * @return Title The page where the question should be posted.
	 */
	abstract protected function getTargetTitle();

	/**
	 * @return IContextSource
	 */
	final protected function getContext() {
		return $this->context;
	}

	/**
	 * The preference name where the posted question will be stored.
	 *
	 * @return string
	 */
	abstract protected function getQuestionStoragePref();

	/**
	 * @return Status
	 * @throws \Exception
	 */
	private function checkUserPermissions() {
		$permissionsManager = MediaWikiServices::getInstance()->getPermissionManager();
		$errors = $permissionsManager->getPermissionErrors(
			'edit',
			$this->getContext()->getUser(),
			$this->getTargetTitle()
		);

		if ( count( $errors ) ) {
			$key = array_shift( $errors[0] );
			$message = $this->getContext()->msg( $key )
				->params( $errors[0] )
				->parse();
			return Status::newFatal( $message );
		}
		return Status::newGood();
	}

	/**
	 * @param Content $content
	 * @param string $summary
	 * @return Status
	 * @throws MWException
	 * @throws FatalError
	 */
	private function runEditFilterMergedContentHook( Content $content, $summary ) {
		$derivativeContext = new DerivativeContext( $this->getContext() );
		$derivativeContext->setTitle( $this->getTargetTitle() );
		$derivativeContext->setWikiPage( WikiPage::factory( $this->getTargetTitle() ) );
		$status = new Status();
		if ( !Hooks::run( 'EditFilterMergedContent', [
			$derivativeContext,
			$content,
			$status,
			$summary,
			$derivativeContext->getUser(),
			false
		] ) ) {
			if ( $status->isGood() ) {
				$status->fatal( 'hookaborted' );
			}
			return $status;
		};
		return $status;
	}

}
