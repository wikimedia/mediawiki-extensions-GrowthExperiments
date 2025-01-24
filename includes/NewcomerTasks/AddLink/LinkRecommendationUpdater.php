<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use ChangeTags;
use Exception;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\WikiConfigException;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Language\RawMessage;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\PageProps;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\Title\Title;
use MediaWiki\Utils\MWTimestamp;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Handles creating or updating a link recommendation entry.
 * This includes fetching a recommendation from the service, validating it, and updating
 * the database and the search index.
 */
class LinkRecommendationUpdater {

	private LoggerInterface $logger;
	private IConnectionProvider $connectionProvider;
	private RevisionStore $revisionStore;
	private NameTableStore $changeDefNameTableStore;
	private PageProps $pageProps;
	private ConfigurationLoader $configurationLoader;
	private ChangeTagsStore $changeTagsStore;
	/**
	 * @var callable returning {@link \CirrusSearch\WeightedTagsUpdater}
	 */
	private $weightedTagsUpdaterProvider;
	private LinkRecommendationStore $linkRecommendationStore;
	private LinkRecommendationProvider $linkRecommendationProvider;
	private ?LinkRecommendationTaskType $linkRecommendationTaskType = null;

	/**
	 * @param LoggerInterface $logger
	 * @param IConnectionProvider $connectionProvider
	 * @param RevisionStore $revisionStore
	 * @param NameTableStore $changeDefNameTableStore
	 * @param PageProps $pageProps
	 * @param ChangeTagsStore $changeTagsStore
	 * @param ConfigurationLoader $configurationLoader
	 * @param callable(): \CirrusSearch\WeightedTagsUpdater $weightedTagsUpdaterProvider
	 * @param LinkRecommendationProvider $linkRecommendationProvider Note that this needs to be
	 *   the uncached provider, as caching is done by LinkRecommendationUpdater.
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct(
		LoggerInterface $logger,
		IConnectionProvider $connectionProvider,
		RevisionStore $revisionStore,
		NameTableStore $changeDefNameTableStore,
		PageProps $pageProps,
		ChangeTagsStore $changeTagsStore,
		ConfigurationLoader $configurationLoader,
		callable $weightedTagsUpdaterProvider,
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkRecommendationStore $linkRecommendationStore
	) {
		$this->logger = $logger;
		$this->connectionProvider = $connectionProvider;
		$this->revisionStore = $revisionStore;
		$this->changeDefNameTableStore = $changeDefNameTableStore;
		$this->pageProps = $pageProps;
		$this->changeTagsStore = $changeTagsStore;

		$this->configurationLoader = $configurationLoader;
		$this->weightedTagsUpdaterProvider = $weightedTagsUpdaterProvider;
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
		$revId = $lastRevision->getId();
		$recommendationState = $this->linkRecommendationStore->getRecommendationStateByRevision(
			$revId,
			IDBAccessObject::READ_LATEST,
		);
		if ( $recommendationState === LinkRecommendationStore::RECOMMENDATION_AVAILABLE ) {
			return $this->failure( 'link recommendation already stored' );
		}
		if ( $recommendationState === LinkRecommendationStore::RECOMMENDATION_NOT_AVAILABLE && !$force ) {
			return $this->failure( 'link recommendation known to not exist' );
		}

		$recommendationStatus = $this->linkRecommendationProvider->getDetailed( $title,
			$this->getLinkRecommendationTaskType() );
		if ( !$recommendationStatus->isGood() ) {
			if (
				$recommendationStatus->getNotGoodCause() ===
				LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_ALL_RECOMMENDATIONS_PRUNED &&
				$recommendationStatus->getNumberOfPrunedRedLinks() === 0
			) {
				$this->linkRecommendationStore->insertNoLinkRecommendationFound( $title->getArticleID(), $revId );
			}
			// Returning a StatusValue that is not good is always an error for the provider. When returning it
			// from this class, it isn't necessarily interpreted that way.
			$recommendationStatus->setOK( false );
			return $recommendationStatus;
		}

		$recommendation = $recommendationStatus->getLinkRecommendation();
		$status = $this->checkRaceConditions( $recommendation, $lastRevision, $force );
		if ( !$status->isOK() ) {
			return $status;
		}
		$status = $this->checkTaskTypeCriteria( $recommendation, $force );
		if ( !$status->isOK() ) {
			if ( $recommendationStatus->getNumberOfPrunedRedLinks() === 0 ) {
				$this->linkRecommendationStore->insertNoLinkRecommendationFound( $title->getArticleID(), $revId );
			}
			return $status;
		}

		// If an error happens later, uncommitted DB writes get discarded, while
		// updateCirrusSearchIndex() is immediate. Minimize the likelihood of the DB
		// and the search index getting out of sync by wrapping the insert into a
		// transaction (in general start/endAtomic doesn't guarantee that but this method
		// will usually be called from maintenance scripts).
		$db = $this->linkRecommendationStore->getDB( DB_PRIMARY );
		$db->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );
		$this->linkRecommendationStore->insertExistingLinkRecommendation( $recommendation );

		$pageIdentity = new PageIdentityValue(
			$lastRevision->getPageId( $lastRevision->getWikiId() ),
			$lastRevision->getPage()->getNamespace(),
			$lastRevision->getPage()->getDBkey(),
			$lastRevision->getWikiId()
		);

		try {
			( $this->weightedTagsUpdaterProvider )()->updateWeightedTags(
				$pageIdentity,
				LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX
			);
		} catch ( Exception $e ) {
			$db->cancelAtomic( __METHOD__ );

			$this->logger->error( __METHOD__ . ' failed to update weighted tags', [
				'exception' => $e,
				'pageTitle' => $title->getPrefixedText(),
			] );
			return Status::newFatal(
				'Failed to request weighted tags update',
				LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX,
				(string)$e
			);
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
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$tags = $this->changeTagsStore->getTagsWithData( $dbr, null, $revision->getId() );
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
			$revertedAddLinkEditCount = $dbr->newSelectQueryBuilder()
				->from( 'revision' )
				->join( 'change_tag', null, [ 'rev_id = ct_rev_id' ] )
				->where( [
					'rev_page' => $title->getArticleID(),
					$dbr->expr( 'rev_id', '<=', (int)$revertTagData['newestRevertedRevId'] ),
					$dbr->expr( 'rev_id', '>=', (int)$revertTagData['oldestRevertedRevId'] ),
					'ct_tag_id' => $linkRecommendationChangeTagId,
				] )
				->caller( __METHOD__ )
				->fetchRowCount();
			if ( $revertedAddLinkEditCount > 0 ) {
				return $this->failure( 'last edit reverts a link recommendation edit' );
			}
		}
		return StatusValue::newGood();
	}

	/**
	 * @param LinkRecommendation $recommendation
	 * @param RevisionRecord $revision The current revision of the page the recommendation is for.
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return StatusValue Success status. Note that the error messages are not intended
	 *   for users (and as such not localizable).
	 */
	private function checkRaceConditions(
		LinkRecommendation $recommendation,
		RevisionRecord $revision,
		bool $force
	): StatusValue {
		if ( $recommendation->getRevisionId() !== $revision->getId() ) {
			// Some kind of race condition? Generating another task is easy so just discard this.
			return $this->failure( 'revision ID mismatch' );
		}

		// T291253
		if (
			!$force &&
			$this->linkRecommendationStore->hasSubmission(
				$recommendation,
				IDBAccessObject::READ_LATEST
			)
		) {
			return $this->failure( 'submission already exists for revision ' . $revision->getId() );
		}

		// We could check here for more race conditions, ie. whether the revision in the
		// recommendation matches the live revision. But there are plenty of other ways for race
		// conditions to happen, so we'll have to deal with them on the client side anyway. No
		// point in getting a primary database connection just for that.

		return StatusValue::newGood();
	}

	/**
	 * Validate a recommendation against the criteria in the task type
	 * @param LinkRecommendation $recommendation
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return StatusValue Success status. Note that the error messages are not intended
	 *   for users (and as such not localizable).
	 */
	private function checkTaskTypeCriteria(
		LinkRecommendation $recommendation,
		bool $force
	): StatusValue {
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
			 || ( !$force && $goodLinkCount < $this->getLinkRecommendationTaskType()->getMinimumLinksPerTask() )
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
			$taskTypes = $this->configurationLoader->getTaskTypes() +
				$this->configurationLoader->getDisabledTaskTypes();
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
