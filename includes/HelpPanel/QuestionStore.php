<?php

namespace GrowthExperiments\HelpPanel;

use Flow\Container;
use JobQueueGroup;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Json\FormatJson;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use UserOptionsUpdateJob;
use Wikimedia\Rdbms\IDBAccessObject;

class QuestionStore {

	private const BLOB_SIZE = 65535;
	private const QUESTION_CHAR_LIMIT = 60;
	private const MAX_QUESTIONS = 3;

	/**
	 * @var User
	 */
	private $user;
	/**
	 * @var string
	 */
	private $preference;
	/**
	 * @var RevisionStore
	 */
	private $revisionStore;
	/**
	 * @var Language
	 */
	private $language;
	/**
	 * @var UserOptionsManager
	 */
	private $userOptionsManager;
	/**
	 * @var UserOptionsLookup
	 */
	private $userOptionsLookup;
	/**
	 * @var JobQueueGroup
	 */
	private $jobQueueGroup;
	/**
	 * @var bool
	 */
	private $wasPosted;

	/**
	 * @param User $user
	 * @param string $preference
	 * @param RevisionStore $revisionStore
	 * @param Language $language
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param JobQueueGroup $jobQueueGroup
	 * @param bool $wasPosted
	 */
	public function __construct(
		$user,
		$preference,
		RevisionStore $revisionStore,
		Language $language,
		UserOptionsManager $userOptionsManager,
		UserOptionsLookup $userOptionsLookup,
		JobQueueGroup $jobQueueGroup,
		$wasPosted
	) {
		$this->user = $user;
		$this->preference = $preference;
		$this->revisionStore = $revisionStore;
		$this->language = $language;
		$this->userOptionsManager = $userOptionsManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->wasPosted = $wasPosted;
	}

	/**
	 * Add the content to the user preference.
	 * @param QuestionRecord $question
	 */
	public function add( QuestionRecord $question ) {
		$question = $this->assignArchiveUrl( $question );
		$trimmedQuestion = $this->trimQuestion( $question );
		$questions = $this->prependQuestion( $trimmedQuestion );
		$this->write( $questions );
	}

	/**
	 * @return QuestionRecord[]
	 */
	public function loadQuestions() {
		$pref = $this->userOptionsLookup->getOption( $this->user, $this->preference );
		if ( !$pref ) {
			return [];
		}
		$questions = FormatJson::decode( $pref, true );
		if ( !is_array( $questions ) ) {
			return [];
		}
		$questions = array_filter( $questions );
		$questionRecords = [];
		foreach ( $questions as $question ) {
			$questionRecords[] = QuestionRecord::newFromArray( $question );

		}
		return $questionRecords;
	}

	/**
	 * @return QuestionRecord[]
	 */
	public function loadQuestionsAndUpdate() {
		$questionRecords = $this->loadQuestions();
		if ( !count( $questionRecords ) ) {
			return [];
		}
		$checkedQuestionRecords = [];
		$needsUpdate = false;
		foreach ( $questionRecords as $questionRecord ) {
			$checkedRecord = clone $questionRecord;

			if ( $questionRecord->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
				$checkedRecord->setVisible( $this->isRevisionVisible( $checkedRecord ) );
				$checkedRecord->setArchived( !$this->questionExistsOnPage( $checkedRecord ) );
				if ( !$checkedRecord->getTimestamp() ) {
					// Some records did not have timestamps (T223338); backfill the
					// timestamp if it's not set.
					$checkedRecord->setTimestamp( (int)wfTimestamp( TS_UNIX ) );
				}
			} elseif ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) &&
				$questionRecord->getContentModel() === CONTENT_MODEL_FLOW_BOARD ) {
				$workflowLoaderFactory = Container::get( 'factory.loader.workflow' );
				$topicTitle = Title::newFromText( $checkedRecord->getRevId(), NS_TOPIC );
				$loader = $workflowLoaderFactory->createWorkflowLoader( $topicTitle );
				$topicBlock = $loader->getBlocks()['topic'];
				$topicBlock->init( RequestContext::getMain(), 'view-topic' );
				$output = $topicBlock->renderApi( [] );
				$deletedOrSuppressed = isset( $output['errors']['permissions'] );
				$checkedRecord->setVisible( !$deletedOrSuppressed );
				if ( !$deletedOrSuppressed ) {
					$topicRoot = $output['roots'][0];
					$topicRootRev = $output['posts'][$topicRoot][0];
					$topic = $output['revisions'][$topicRootRev];
					$checkedRecord->setArchived( ( $topic['moderateState'] ?? '' ) === 'hide' );
					$checkedRecord->setArchiveUrl( $checkedRecord->getResultUrl() );
				}
			}

			$checkedQuestionRecords[] = $checkedRecord;
			if ( $questionRecord->jsonSerialize() !== $checkedRecord->jsonSerialize() ) {
				$needsUpdate = true;
			}
		}
		if ( $needsUpdate ) {
			$this->write( $checkedQuestionRecords );
		}
		return $this->excludeHiddenQuestions( $checkedQuestionRecords );
	}

	/**
	 * @param QuestionRecord[] $questionRecords
	 * @return QuestionRecord[]
	 */
	private function excludeHiddenQuestions( array $questionRecords ) {
		return array_filter( $questionRecords, static function ( QuestionRecord $questionRecord ) {
			return $questionRecord->isVisible();
		} );
	}

	/**
	 * Does the question exists on the page specified by $questionRecord->resultUrl?
	 * Note this is not always the same as asking whether it exists on the page that contains
	 * $questionRecord->revId.
	 * @param QuestionRecord $questionRecord
	 * @return bool
	 */
	private function questionExistsOnPage( QuestionRecord $questionRecord ) {
		$revision = $this->revisionStore->getRevisionById( $questionRecord->getRevId() );
		if ( !$revision ) {
			return false;
		}

		$oldUrl = explode( '#', $questionRecord->getResultUrl() )[0];
		$newUrl = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() )->getLinkURL();
		if ( $oldUrl !== $newUrl ) {
			return false;
		}

		$latestPageRevision = $this->revisionStore->getRevisionByTitle(
			$revision->getPageAsLinkTarget()
		);
		/** @var TextContent|null $content */
		$content = $latestPageRevision->getContent( SlotRecord::MAIN );
		return $content instanceof TextContent
			&& strpos( $content->getText(), $questionRecord->getSectionHeader() ) !== false;
	}

	/**
	 * @param QuestionRecord[] $questionRecords
	 */
	private function write( array $questionRecords ) {
		$formattedQuestions = $this->encodeQuestionsToJson( $questionRecords );
		$storage = $this->preference;
		if ( strlen( $formattedQuestions ) >= self::BLOB_SIZE ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )
				->warning( 'Unable to save question records for user {userId} because they are too big.',
					[
						'userId' => $this->user->getId(),
						'storage' => $storage,
						'length' => strlen( $formattedQuestions )
					] );
			return;
		}
		if ( $this->wasPosted ) {
			$this->saveToUserSettings( $storage, $formattedQuestions );
		} else {
			$this->saveToUserSettingsWithJob( $storage, $formattedQuestions );
		}
	}

	private function saveToUserSettings( $storage, $formattedQuestions ) {
		$updateUser = $this->user->getInstanceForUpdate();
		$this->userOptionsManager->setOption( $updateUser, $storage, $formattedQuestions );
		$updateUser->saveSettings();
	}

	private function saveToUserSettingsWithJob( $storage, $formattedQuestions ) {
		$job = new UserOptionsUpdateJob( [
			'userId' => $this->user->getId(),
			'options' => [ $storage => $formattedQuestions ]
		] );
		$this->jobQueueGroup->lazyPush( $job );
	}

	private function encodeQuestionsToJson( array $questionRecords ) {
		return FormatJson::encode( $questionRecords, false, FormatJson::ALL_OK );
	}

	private function prependQuestion( QuestionRecord $questionRecord ) {
		$questions = $this->loadQuestions();
		array_unshift( $questions, $questionRecord );
		return array_slice( $questions, 0, self::MAX_QUESTIONS );
	}

	private function assignArchiveUrl( QuestionRecord $questionRecord ) {
		$revision = $this->revisionStore->getRevisionById( $questionRecord->getRevId(),
			$this->wasPosted ? IDBAccessObject::READ_LATEST : IDBAccessObject::READ_NORMAL );
		if ( !$revision ) {
			return $questionRecord;
		}
		$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
		// Hack: Extract fragment from result URL, so we can use it for archive URL.
		$fragment = substr(
			$questionRecord->getResultUrl(),
			strpos( $questionRecord->getResultUrl(), '#' ) + 1
		);
		$questionRecord->setArchiveUrl(
			$title->createFragmentTarget( $fragment )
				->getLinkURL( [ 'oldid' => $questionRecord->getRevId() ] )
		);
		return $questionRecord;
	}

	/**
	 * @param QuestionRecord $question
	 * @return QuestionRecord
	 */
	private function trimQuestion( QuestionRecord $question ) {
		$trimmedQuestionText = $this->language->truncateForVisual(
			$question->getQuestionText(),
			self::QUESTION_CHAR_LIMIT
		);
		$question->setQuestionText( $trimmedQuestionText ?? '' );
		return $question;
	}

	private function isRevisionVisible( QuestionRecord $questionRecord ) {
		$revision = $this->revisionStore->getRevisionById( $questionRecord->getRevId() );
		return $revision && $revision->getVisibility() === 0;
	}

}
