<?php

namespace GrowthExperiments\HelpPanel;

use FormatJson;
use JobQueueGroup;
use Language;
use MediaWiki\Logger\LoggerFactory;
use TextContent;
use UserOptionsUpdateJob;
use Wikimedia\Rdbms\LoadBalancer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use Title;
use User;

class QuestionStore {

	const BLOB_SIZE = 65535;
	const QUESTION_CHAR_LIMIT = 60;
	const MAX_QUESTIONS = 3;

	/**
	 * @var int
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
	 * @var LoadBalancer
	 */
	private $loadBalancer;
	/**
	 * @var Language
	 */
	private $language;
	/**
	 * @var bool
	 */
	private $wasPosted;

	/**
	 * @param User $user
	 * @param string $preference
	 * @param RevisionStore $revisionStore
	 * @param LoadBalancer $loadBalancer
	 * @param Language $language
	 * @param bool $wasPosted
	 */
	public function __construct(
		$user,
		$preference,
		RevisionStore $revisionStore,
		LoadBalancer $loadBalancer,
		Language $language,
		$wasPosted
	) {
		$this->user = $user;
		$this->preference = $preference;
		$this->revisionStore = $revisionStore;
		$this->loadBalancer = $loadBalancer;
		$this->language = $language;
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
		$pref = $this->user->getOption( $this->preference );
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
			$checkedRecord->setVisible( $this->isRevisionVisible( $checkedRecord ) );
			$checkedRecord->setArchived( !$this->questionExistsOnPage( $checkedRecord ) );
			// b/c, archiveUrl is now set when first added to the store.
			// todo: Remove with wmf.5
			if ( $checkedRecord->isArchived() && !$checkedRecord->getArchiveUrl() ) {
				$checkedRecord = $this->assignArchiveUrl( $checkedRecord );
			}
			if ( !$checkedRecord->getTimestamp() ) {
				// Some records did not have timestamps (T223338); backfill the
				// timestamp if it's not set.
				$checkedRecord->setTimestamp( wfTimestamp() );
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
		return array_filter( $questionRecords, function ( QuestionRecord $questionRecord ) {
			return $questionRecord->isVisible();
		} );
	}

	/**
	 * @param QuestionRecord $questionRecord
	 * @return bool
	 */
	private function questionExistsOnPage( QuestionRecord $questionRecord ) {
		$revision = $this->revisionStore->loadRevisionFromId(
			$this->loadBalancer->getConnection( DB_REPLICA ),
			$questionRecord->getRevId()
		);
		if ( !$revision ) {
			return false;
		}

		$latestPageRevision = $this->revisionStore->getRevisionByTitle(
			$revision->getPageAsLinkTarget()
		);
		/** @var TextContent $content */
		$content = $latestPageRevision->getContent( SlotRecord::MAIN );
		// @phan-suppress-next-line PhanUndeclaredMethod
		return strpos( $content->getText(), $questionRecord->getSectionHeader() ) !== false;
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
		$updateUser->setOption( $storage, $formattedQuestions );
		$updateUser->saveSettings();
	}

	private function saveToUserSettingsWithJob( $storage, $formattedQuestions ) {
		$job = new UserOptionsUpdateJob( [
			'userId' => $this->user->getId(),
			'options' => [ $storage => $formattedQuestions ]
		] );
		JobQueueGroup::singleton()->lazyPush( $job );
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
		$revision = $this->revisionStore->loadRevisionFromId(
			$this->loadBalancer->getConnection( $this->wasPosted ? DB_MASTER : DB_REPLICA ),
			$questionRecord->getRevId()
		);
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
				->getFullURL( [ 'oldid' => $questionRecord->getRevId() ] )
		);
		return $questionRecord;
	}

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
