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
	private $postedOnTimestamp;
	/**
	 * @var QuestionRecord[]
	 */
	private $existingQuestionsByUser;
	/**
	 * @var string
	 */
	private $body;
	/**
	 * @var string
	 */
	private $sectionHeaderWithTimestamp;

	/**
	 * QuestionPoster constructor.
	 * @param IContextSource $context
	 * @param string $body
	 * @param string $relevantTitle
	 * @throws MWException
	 */
	public function __construct(
		IContextSource $context, $body, $relevantTitle = ''
	) {
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
		$this->body = $body;
	}

	/**
	 * @return Status
	 * @throws MWException
	 * @throws \Exception
	 */
	public function submit() {
		$this->postedOnTimestamp = wfTimestamp();
		$permissionStatus = $this->checkPermissions();
		if ( !$permissionStatus->isGood() ) {
			return $permissionStatus;
		}
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			$this->getQuestionStoragePref()
		);
		$this->existingQuestionsByUser = $questionStore->loadQuestions();
		$this->setSectionHeaderWithTimestamp();
		$this->pageUpdater->addTag( $this->getTag() );
		$this->pageUpdater->setContent( SlotRecord::MAIN, $this->getContent() );
		$newRev = $this->pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( $this->getSectionHeader() )
		);
		if ( !$this->pageUpdater->getStatus()->isGood() ) {
			return $this->pageUpdater->getStatus();
		}
		$this->revisionId = $newRev->getId();
		$this->setResultUrl();

		$question = new QuestionRecord(
			$this->getBody(),
			$this->getSectionHeaderWithTimestamp(),
			$this->revisionId,
			$this->getPostedOnTimestamp(),
			$this->getResultUrl()
		);
		QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			$this->getQuestionStoragePref()
		)->add( $question );
		return Status::newGood();
	}

	private function getNumberedSectionHeaderIfDuplicatesExist( $sectionHeader ) {
		$sectionHeaders = array_map(
			function ( QuestionRecord $questionRecord ) {
				return $questionRecord->getSectionHeader();
			},
			$this->existingQuestionsByUser
		);
		$counter = 1;
		while ( in_array( $counter === 1 ? $sectionHeader : "$sectionHeader ($counter)",
			$sectionHeaders ) ) {
			$counter++;
		}
		return $counter === 1 ? $sectionHeader : $sectionHeader . ' (' . $counter . ')';
	}

	private function checkPermissions() {
		$userPermissionStatus = $this->checkUserPermissions();
		if ( !$userPermissionStatus->isGood() ) {
			return $userPermissionStatus;
		}
		$content = $this->getContent();
		$editFilterMergedContentHookStatus = $this->runEditFilterMergedContentHook(
			$content,
			$this->getSectionHeader()
		);
		if ( !$editFilterMergedContentHookStatus->isGood() ) {
			return $editFilterMergedContentHookStatus;
		}
		return Status::newGood();
	}

	/**
	 * The tag to add to the edit via PageUpdater.
	 */
	abstract protected function getTag();

	/**
	 * @return Content|string|null
	 * @throws MWException
	 */
	public function getContent() {
		$content = new WikitextContent(
			$this->addSignature( $this->getBody() )
		);
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
	private function addSignature( $body ) {
		if ( strpos( $body, '~~~~' ) === false ) {
			$body .= " --~~~~";
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
		// Check if user can change their email; don't allow changing email when it's already set
		if ( $existingEmail !== '' || !Util::canSetEmail( $user, $newEmail, (bool)$existingEmail ) ) {
			return Status::newFatal( 'growthexperiments-help-panel-questionposter-user-cannot-set-email' );
		}
		// Set new email and send confirmation.
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
	 * Set the section header with a timestamp and number.
	 *
	 * THe number is appended only if duplicate headers exist, which can happen when questions
	 * are posted within the same minute.
	 */
	private function setSectionHeaderWithTimestamp() {
		$this->sectionHeaderWithTimestamp = $this->getSectionHeader() . ' ' .
			$this->getContext()->msg( 'parentheses' )
				->plaintextParams( $this->getFormattedPostedOnTimestamp() )
				->inContentLanguage()->escaped();
		$this->sectionHeaderWithTimestamp = $this->getNumberedSectionHeaderIfDuplicatesExist(
			$this->sectionHeaderWithTimestamp
		);
	}

	/**
	 * @return string
	 */
	private function getSectionHeaderWithTimestamp() {
		return $this->sectionHeaderWithTimestamp;
	}

	/**
	 * @return string
	 */
	private function getPostedOnTimestamp() {
		return $this->postedOnTimestamp;
	}

	/*
	 * Timezone adjustment, site default format, and site default time zone are used for formatting.
	 */
	private function getFormattedPostedOnTimestamp() {
		return MediaWikiServices::getInstance()->getContentLanguage()
			->timeanddate( $this->getPostedOnTimestamp(), true, false, '' );
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
		$derivativeContext->setConfig( MediaWikiServices::getInstance()->getMainConfig() );
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

	private function getBody() {
		return $this->body;
	}

}
