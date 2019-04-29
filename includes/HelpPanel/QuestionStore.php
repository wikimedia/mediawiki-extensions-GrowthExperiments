<?php

namespace GrowthExperiments\HelpPanel;

use DeferredUpdates;
use FormatJson;
use Language;
use MediaWiki\Logger\LoggerFactory;
use TextContent;
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
	 * @var LoadBalancer
	 */
	private $loadBalancer;
	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @param User $user
	 * @param string $preference
	 * @param RevisionStore $revisionStore
	 * @param LoadBalancer $loadBalancer
	 * @param Language $language
	 */
	public function __construct(
		User $user,
		$preference,
		RevisionStore $revisionStore,
		LoadBalancer $loadBalancer,
		Language $language
	) {
		$this->user = $user;
		$this->preference = $preference;
		$this->revisionStore = $revisionStore;
		$this->loadBalancer = $loadBalancer;
		$this->language = $language;
	}

	/**
	 * Add the content to the user preference.
	 * @param QuestionRecord $question
	 */
	public function add( QuestionRecord $question ) {
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
			$questionRecord = QuestionRecord::newFromArray( $question );
			if ( $this->isRevisionVisible( $questionRecord ) ) {
				$questionRecords[] = $questionRecord;
			}
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
			if ( !$questionRecord->isArchived() ) {
				$questionRecord->setArchived(
					!$this->questionExistsOnPage( $questionRecord )
				);
				if ( !$this->isRevisionVisible( $questionRecord ) ) {
					$needsUpdate = true;
					continue;
				}
				if ( $questionRecord->isArchived() ) {
					$questionRecord = $this->setArchiveUrl( $questionRecord );
					$needsUpdate = true;
				}
			}
			$checkedQuestionRecords[] = $questionRecord;
		}
		if ( $needsUpdate ) {
			$this->updateStoredQuestions( $checkedQuestionRecords );
		}
		return $checkedQuestionRecords;
	}

	/**
	 * @param QuestionRecord[] $questionRecords
	 */
	private function updateStoredQuestions( $questionRecords ) {
		// TODO: When https://gerrit.wikimedia.org/r/c/mediawiki/core/+/499985 lands, we can
		// push a new UserOptionsUpdateJob to update the question. For now we'll use a deferred
		// update even though transaction profiler will complain.
		DeferredUpdates::addCallableUpdate( function () use ( $questionRecords ) {
			$this->write( $questionRecords );
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
		$formattedQuestions = FormatJson::encode( $questionRecords, false, FormatJson::ALL_OK );
		if ( strlen( $formattedQuestions ) <= self::BLOB_SIZE ) {
			$userLatest = $this->user->getInstanceForUpdate();
			$userLatest->setOption( $this->preference, $formattedQuestions );
			$userLatest->saveSettings();
		} else {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				'Unable to save question records for user {userId} because they are too big.',
				[
					'userId' => $this->user->getId() ,
					'storage' => $this->preference,
					'length' => strlen( $formattedQuestions )
				]
			);
		}
	}

	private function prependQuestion( QuestionRecord $questionRecord ) {
		$questions = $this->loadQuestions();
		array_unshift( $questions, $questionRecord );
		return array_slice( $questions, 0, self::MAX_QUESTIONS );
	}

	private function setArchiveUrl( QuestionRecord $questionRecord ) {
		$revision = $this->revisionStore->loadRevisionFromId(
			$this->loadBalancer->getConnection( DB_REPLICA ),
			$questionRecord->getRevId()
		);
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
		return $this->revisionStore
			->getRevisionById( $questionRecord->getRevId() )
			->getVisibility() === 0;
	}

}
