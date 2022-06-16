<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use ChangeTags;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\SearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\WikiConfigException;
use IDBAccessObject;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\NameTableStore;
use MWTimestamp;
use PageProps;
use RawMessage;
use Status;
use StatusValue;
use Title;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IDatabase;
use WikitextContent;

/**
 * Handles creating or updating a link recommendation entry.
 * This includes fetching a recommendation from the service, validating it, and updating
 * the database and the search index.
 */
class LinkRecommendationUpdater {

	/** @var IDatabase */
	private $dbr;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var NameTableStore */
	private $changeDefNameTableStore;

	/** @var PageProps */
	private $pageProps;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var SearchIndexUpdater */
	private $searchIndexUpdater;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/** @var LinkRecommendationTaskType */
	private $linkRecommendationTaskType;

	/**
	 * @param IDatabase $dbr Read handle to the main database.
	 * @param RevisionStore $revisionStore
	 * @param NameTableStore $changeDefNameTableStore
	 * @param PageProps $pageProps
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchIndexUpdater $searchIndexUpdater
	 * @param LinkRecommendationProvider $linkRecommendationProvider Note that this needs to be
	 *   the uncached provider, as caching is done by LinkRecommendationUpdater.
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct(
		IDatabase $dbr,
		RevisionStore $revisionStore,
		NameTableStore $changeDefNameTableStore,
		PageProps $pageProps,
		ConfigurationLoader $configurationLoader,
		SearchIndexUpdater $searchIndexUpdater,
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkRecommendationStore $linkRecommendationStore
	) {
		$this->dbr = $dbr;
		$this->revisionStore = $revisionStore;
		$this->changeDefNameTableStore = $changeDefNameTableStore;
		$this->pageProps = $pageProps;
		$this->configurationLoader = $configurationLoader;
		$this->searchIndexUpdater = $searchIndexUpdater;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkRecommendationProvider = $linkRecommendationProvider;
	}

	/**
	 * Evaluate a task candidate and generate the task if the candidate is viable.
	 * If a link recommendation task already exists for the given page, it will be overwritten.
	 * @param Title $title
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return StatusValue Success status. Note that the error messages are not intended
	 *   for users (and as such not localizable).
	 * @throws WikiConfigException if the task type is not properly configured.
	 * @throws DBReadOnlyError
	 */
	public function processCandidate( Title $title, bool $force = false ): StatusValue {
		$lastRevision = $this->revisionStore->getRevisionByTitle( $title );
		$status = $this->evaluateTitle( $title, $lastRevision, $force );
		if ( !$status->isOK() ) {
			return $status;
		}

		// Prevent infinite loop. Cirrus updates are not realtime so pages we have
		// just created recommendations for will be included again in the next batch.
		// Skip them to ensure $recommendationsFound is only nonzero then we have
		// actually added a new recommendation.
		// FIXME there is probably a better way to do this via search offsets.
		if ( $this->linkRecommendationStore->getByRevId( $lastRevision->getId(),
			IDBAccessObject::READ_LATEST )
		) {
			return $this->failure( 'link recommendation already stored' );
		}

		$recommendation = $this->linkRecommendationProvider->get( $title,
			$this->getLinkRecommendationTaskType() );
		if ( $recommendation instanceof StatusValue ) {
			// Returning a StatusValue is always an error for the provider. When returning it
			// from this class, it isn't necessarily interpreted that way.
			$recommendation->setOK( false );
			return $recommendation;
		}
		$status = $this->evaluateRecommendation( $recommendation, $lastRevision, $force );
		if ( !$status->isOK() ) {
			return $status;
		}

		// If an error happens later, uncommitted DB writes get discarded, while
		// updateCirrusSearchIndex() is immediate. Minimize the likelihood of the DB
		// and the search index getting out of sync by wrapping the insert into a
		// transaction (in general start/endAtomic doesn't guarantee that but this method
		// will usually be called from maintenance scripts).
		$db = $this->linkRecommendationStore->getDB( DB_PRIMARY );
		$db->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );
		$this->linkRecommendationStore->insert( $recommendation );
		$status = $this->searchIndexUpdater->update( $lastRevision );
		if ( !$status->isOK() ) {
			$db->cancelAtomic( __METHOD__ );
			return $status;
		}
		$db->endAtomic( __METHOD__ );
		return StatusValue::newGood();
	}

	/**
	 * Check all conditions which are not related to the recommendation.
	 * @param Title $title The title for which a recommendation is being requested.
	 * @param RevisionRecord|null $revision The current revision of the title.
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return StatusValue Success status. Note that the error messages are not intended
	 *   for users (and as such not localizable).
	 */
	private function evaluateTitle( Title $title, ?RevisionRecord $revision, bool $force ): StatusValue {
		// 1. the revision must exist and the mwaddlink service must be able to interpret it.
		if ( $revision === null ) {
			// Maybe the article has just been deleted and the search index is behind?
			return $this->failure( 'page not found' );
		}
		$content = $revision->getContent( SlotRecord::MAIN );
		if ( !$content instanceof WikitextContent ) {
			return $this->failure( 'content not found' );
		}

		if ( $force ) {
			return StatusValue::newGood();
		}

		// 2. the article must match size conditions.
		$wordCount = preg_match_all( '/\w+/', $content->getText() );
		if ( $wordCount < $this->getLinkRecommendationTaskType()->getMinimumWordCount() ) {
			return $this->failure( "word count too small ($wordCount)" );
		} elseif ( $wordCount > $this->getLinkRecommendationTaskType()->getMaximumWordCount() ) {
			return $this->failure( "word count too large ($wordCount)" );
		}

		// 3. exclude articles which have been edited very recently.
		$revisionTime = (int)MWTimestamp::convert( TS_UNIX, $revision->getTimestamp() );
		if ( time() - $revisionTime < $this->getLinkRecommendationTaskType()->getMinimumTimeSinceLastEdit() ) {
			return $this->failure( 'minimum time since last edit did not pass' );
		}

		// 4. exclude disambiguation pages.
		if ( $this->pageProps->getProperties( $title, 'disambiguation' ) ) {
			return $this->failure( 'disambiguation page' );
		}

		// 5. exclude pages where the last edit is a link recommendation edit or its revert.
		$tags = ChangeTags::getTagsWithData( $this->dbr, null, $revision->getId() );
		if ( array_key_exists( LinkRecommendationTaskTypeHandler::CHANGE_TAG, $tags ) ) {
			return $this->failure( 'last edit is a link recommendation' );
		}
		$revertTagData = null;
		foreach ( ChangeTags::REVERT_TAGS as $revertTagName ) {
			if ( !empty( $tags[$revertTagName] ) ) {
				$revertTagData = json_decode( $tags[$revertTagName], true );
				break;
			}
		}
		if ( is_array( $revertTagData ) ) {
			$linkRecommendationChangeTagId = $this->changeDefNameTableStore
				->acquireId( LinkRecommendationTaskTypeHandler::CHANGE_TAG );
			$revertedAddLinkEditCount = $this->dbr->selectRowCount(
				[ 'revision', 'change_tag' ],
				'1',
				[
					'rev_id = ct_rev_id',
					'rev_page' => $title->getArticleID(),
					'rev_id <=' . (int)$revertTagData['newestRevertedRevId'],
					'rev_id >=' . (int)$revertTagData['oldestRevertedRevId'],
					'ct_tag_id' => $linkRecommendationChangeTagId,
				],
				__METHOD__
			);
			if ( $revertedAddLinkEditCount > 0 ) {
				return $this->failure( 'last edit reverts a link recommendation edit' );
			}
		}
		return StatusValue::newGood();
	}

	/**
	 * Validate a recommendation against the criteria in the task type and safety checks.
	 * @param LinkRecommendation $recommendation
	 * @param RevisionRecord $revision The current revision of the page the recommendation is for.
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return StatusValue Success status. Note that the error messages are not intended
	 *   for users (and as such not localizable).
	 */
	private function evaluateRecommendation(
		LinkRecommendation $recommendation,
		RevisionRecord $revision,
		bool $force
	): StatusValue {
		if ( $recommendation->getRevisionId() !== $revision->getId() ) {
			// Some kind of race condition? Generating another task is easy so just discard this.
			return $this->failure( 'revision ID mismatch' );
		}

		// T291253
		if ( !$force && $this->linkRecommendationStore->hasSubmission( $recommendation,
			IDBAccessObject::READ_LATEST )
		) {
			return $this->failure( 'submission already exists for revision ' . $revision->getId() );
		}

		// We could check here for more race conditions, ie. whether the revision in the
		// recommendation matches the live revision. But there are plenty of other ways for race
		// conditions to happen, so we'll have to deal with them on the client side anyway. No
		// point in getting a primary database connection just for that.

		$goodLinks = array_filter( $recommendation->getLinks(), function ( LinkRecommendationLink $link ) {
			return $link->getScore() >= $this->getLinkRecommendationTaskType()->getMinimumLinkScore();
		} );
		$recommendation = new LinkRecommendation(
			$recommendation->getTitle(),
			$recommendation->getPageId(),
			$recommendation->getRevisionId(),
			$goodLinks,
			$recommendation->getMetadata()
		);
		$goodLinkCount = count( $recommendation->getLinks() );
		if ( $goodLinkCount === 0
			 || !$force && $goodLinkCount < $this->getLinkRecommendationTaskType()->getMinimumLinksPerTask()
		) {
			return $this->failure( "number of good links too small ($goodLinkCount)" );
		}

		return StatusValue::newGood();
	}

	/**
	 * Internal helper for loading the Add Link task type. Due to the involvement of on-wiki
	 * configuration, this is not available at setup time so it cannot be dependency-injected.
	 * @return LinkRecommendationTaskType
	 * @throws WikiConfigException if the task type is not properly configured.
	 */
	private function getLinkRecommendationTaskType(): LinkRecommendationTaskType {
		if ( !$this->linkRecommendationTaskType ) {
			$taskTypes = $this->configurationLoader->loadTaskTypes();
			if ( $taskTypes instanceof StatusValue ) {
				throw new WikiConfigException( 'Could not load task types: ' .
					Status::wrap( $taskTypes )->getWikiText( false, false, 'en' ) );
			}
			$taskTypes = $this->configurationLoader->getTaskTypes();
			$taskType = $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
			if ( !( $taskType instanceof LinkRecommendationTaskType ) ) {
				throw new WikiConfigException( 'Could not load link recommendation task type' );
			}
			$this->linkRecommendationTaskType = $taskType;
		}
		return $this->linkRecommendationTaskType;
	}

	/**
	 * Convenience shortcut for making StatusValue objects with non-localized messages.
	 * @param string $error
	 * @return StatusValue
	 */
	private function failure( string $error ): StatusValue {
		return StatusValue::newFatal( new RawMessage( $error ) );
	}

}
