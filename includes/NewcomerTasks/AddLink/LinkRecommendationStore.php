<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use DomainException;
use GrowthExperiments\Util;
use MediaWiki\Deferred\LinksUpdate\TemplateLinksTable;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Page\PageRecord;
use MediaWiki\Page\PageStore;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Service that handles access to the link recommendation related database tables.
 */
class LinkRecommendationStore {

	public const RECOMMENDATION_AVAILABLE = 'recommendation_available';
	public const RECOMMENDATION_NOT_AVAILABLE = 'recommendation_not_available';
	public const RECOMMENDATION_UNKNOWN = 'recommendation_unknown';

	private IConnectionProvider $connectionProvider;
	private ILoadBalancer $growthLoadBalancer;
	private TitleFactory $titleFactory;
	private LinkBatchFactory $linkBatchFactory;
	private PageStore $pageStore;
	private LoggerInterface $logger;

	public function __construct(
		IConnectionProvider $connectionProvider,
		ILoadBalancer $growthLoadBalancer,
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		PageStore $pageStore,
		LoggerInterface $logger
	) {
		$this->connectionProvider = $connectionProvider;
		$this->growthLoadBalancer = $growthLoadBalancer;
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->pageStore = $pageStore;
		$this->logger = $logger;
	}

	// growthexperiments_link_recommendations

	/**
	 * Get a link recommendation by some condition.
	 * @param array $condition A Database::select() condition array.
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	private function getByCondition( array $condition, int $flags = 0 ): ?LinkRecommendation {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) === IDBAccessObject::READ_LATEST ) {
			$db = $this->getGrowthDB( DB_PRIMARY );
		} else {
			$db = $this->getGrowthDB( DB_REPLICA );
		}
		$row = $db->newSelectQueryBuilder()
			->select( [ 'gelr_page', 'gelr_revision', 'gelr_data' ] )
			->from( 'growthexperiments_link_recommendations' )
			->where( $condition )
			->andWhere( 'gelr_data IS NOT NULL' )
			->caller( __METHOD__ )
			->recency( $flags )
			// $condition is supposed to be unique, but if somehow that isn't the case,
			// use the most up-to-date recommendation.
			->orderBy( 'gelr_revision', SelectQueryBuilder::SORT_DESC )
			->fetchRow();
		if ( $row === false ) {
			return null;
		}
		return $this->getLinkRecommendationsFromRows( [ $row ], $flags )[0] ?? null;
	}

	/**
	 * Get a link recommendation by revision ID.
	 * @param int $revId
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	public function getByRevId( int $revId, int $flags = 0 ): ?LinkRecommendation {
		return $this->getByCondition( [ 'gelr_revision' => $revId ], $flags );
	}

	/**
	 * Get a link recommendation by page ID.
	 * @param int $pageId
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	public function getByPageId( int $pageId, int $flags = 0 ): ?LinkRecommendation {
		return $this->getByCondition( [ 'gelr_page' => $pageId ], $flags );
	}

	/**
	 * Get a link recommendation by link target.
	 * @param LinkTarget $linkTarget
	 * @param int $flags IDBAccessObject flags
	 * @param bool $allowOldRevision When true, return any recommendation for the given page;
	 *   otherwise, only use a recommendation if it's for the current revision.
	 * @return LinkRecommendation|null
	 */
	public function getByLinkTarget(
		LinkTarget $linkTarget,
		int $flags = 0,
		bool $allowOldRevision = false
	): ?LinkRecommendation {
		$title = $this->titleFactory->newFromLinkTarget( $linkTarget );
		if ( $allowOldRevision ) {
			$pageId = $title->getArticleID( $flags );
			if ( $pageId === 0 ) {
				return null;
			}
			return $this->getByPageId( $pageId, $flags );
		} else {
			$revId = $title->getLatestRevID( $flags );
			if ( $revId === 0 ) {
				return null;
			}
			$linkRecommendation = $this->getByRevId( $revId, $flags );
			if ( !$linkRecommendation ) {
				$this->logger->debug( 'Unable to find link recommendation for title {title} ' .
					'with getByRevId().', [
					'title' => $title->getPrefixedDBkey(),
					'revId' => $revId,
					'trace' => \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ),
				] );
			}
			return $linkRecommendation;
		}
	}

	/**
	 * TODO: replace return values with ENUM once PHP 8.1 is available
	 * @param int $revId
	 * @param int $flags IDBAccessObject flags
	 * @return string One of the LinkRecommendationStore::RECOMMENDATION_* constants
	 */
	public function getRecommendationStateByRevision( int $revId, int $flags = 0 ): string {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) === IDBAccessObject::READ_LATEST ) {
			$db = $this->getGrowthDB( DB_PRIMARY );
		} else {
			$db = $this->getGrowthDB( DB_REPLICA );
		}
		$recommendationData = $db->newSelectQueryBuilder()
			->select( [ 'gelr_data' ] )
			->from( 'growthexperiments_link_recommendations' )
			->where( [ 'gelr_revision' => $revId ] )
			->caller( __METHOD__ )
			->recency( $flags )
			->fetchField();

		if ( $recommendationData === false ) {
			return self::RECOMMENDATION_UNKNOWN;
		}
		if ( $recommendationData === null ) {
			return self::RECOMMENDATION_NOT_AVAILABLE;
		}
		return self::RECOMMENDATION_AVAILABLE;
	}

	/**
	 * Iterate through all link recommendations, in ascending page ID order.
	 * @param int $limit
	 * @param int &$fromPageId Starting page ID. Will be set to the last fetched page ID plus one.
	 *   (This cannot be done on the caller side because records with non-existing page IDs are
	 *   omitted from the result.) Will be set to false when there are no more rows.
	 * @return LinkRecommendation[]
	 */
	public function getAllExistingRecommendations( int $limit, int &$fromPageId ): array {
		$dbr = $this->getGrowthDB( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'gelr_revision', 'gelr_page', 'gelr_data' ] )
			->from( 'growthexperiments_link_recommendations' )
			->where( $dbr->expr( 'gelr_page', '>=', $fromPageId ) )
			->andWhere( 'gelr_data IS NOT NULL' )
			->orderBy( 'gelr_page ASC' )
			->limit( $limit )
			->caller( __METHOD__ )->fetchResultSet();
		$rows = iterator_to_array( $res );
		$fromPageId = ( $res->numRows() === $limit ) ? end( $rows )->gelr_page + 1 : false;
		reset( $rows );
		return $this->getLinkRecommendationsFromRows( $rows );
	}

	/**
	 * Method to get all recommendation entries, both for pages with a recommendation and those without
	 *
	 * @param int $limit
	 * @param int &$fromPageId Starting page ID. Will be set to the last fetched page ID plus one.
	 *                         Will be set to false when there are no more rows.
	 *
	 * @return (LinkRecommendation|NullLinkRecommendation)[]
	 */
	public function getAllRecommendationEntries( int $limit, int &$fromPageId ): array {
		$dbr = $this->getGrowthDB( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'gelr_revision', 'gelr_page', 'gelr_data' ] )
			->from( 'growthexperiments_link_recommendations' )
			->where( $dbr->expr( 'gelr_page', '>=', $fromPageId ) )
			->orderBy( 'gelr_page ASC' )
			->limit( $limit )
			->caller( __METHOD__ )->fetchResultSet();
		$rows = iterator_to_array( $res );
		$fromPageId = ( $res->numRows() === $limit ) ? end( $rows )->gelr_page + 1 : false;
		reset( $rows );

		return $this->getAllRecommendationsFromAllRows( $rows );
	}

	/**
	 * Given a set of page IDs, return the ones which have a valid link recommendation
	 * (valid as in it's for the latest revision).
	 * @param int[] $pageIds
	 * @return int[]
	 */
	public function filterPageIds( array $pageIds ): array {
		$pageRecords = $this->pageStore
			->newSelectQueryBuilder()
			->wherePageIds( $pageIds )
			->caller( __METHOD__ )
			->fetchPageRecords();

		$conds = [];
		$dbr = $this->growthLoadBalancer->getConnection( DB_REPLICA );
		/** @var PageRecord $pageRecord */
		foreach ( $pageRecords as $pageRecord ) {
			$pageId = $pageRecord->getId();
			$revId = $pageRecord->getLatest();
			if ( !$pageId || !$revId ) {
				continue;
			}
			// $revId can be outdated due to replag; we don't want to delete the record then.
			$conds[] = $dbr->expr( 'gelr_page', '=', $pageId )->and( 'gelr_revision', '>=', $revId );
		}
		return array_map( 'intval', $dbr->newSelectQueryBuilder()
			->select( 'gelr_page' )
			->from( 'growthexperiments_link_recommendations' )
			->where( $dbr->orExpr( $conds ) )
			->andWhere( 'gelr_data IS NOT NULL' )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	/**
	 * List all pages with link recommendations, by page ID.
	 * @param int $limit
	 * @param int|null $from ID to list from, exclusive
	 * @return int[]
	 */
	public function listPageIds( int $limit, ?int $from = null ): array {
		$dbr = $this->growthLoadBalancer->getConnection( DB_REPLICA );
		return array_map( 'intval', $dbr->newSelectQueryBuilder()
			->select( 'gelr_page' )
			->from( 'growthexperiments_link_recommendations' )
			->where( $from ? $dbr->expr( 'gelr_page', '>', $from ) : [] )
			->andWhere( 'gelr_data IS NOT NULL' )
			->groupBy( 'gelr_page' )
			->orderBy( 'gelr_page ASC' )
			->limit( $limit )
			->caller( __METHOD__ )->fetchFieldValues()
		);
	}

	/**
	 * Insert a new link recommendation.
	 */
	public function insertExistingLinkRecommendation( LinkRecommendation $linkRecommendation ): void {
		$pageId = $linkRecommendation->getPageId();
		$revisionId = $linkRecommendation->getRevisionId();
		$row = [
			'gelr_revision' => $revisionId,
			'gelr_page' => $pageId,
			'gelr_data' => json_encode( $linkRecommendation->toArray() ),
		];
		$this->growthLoadBalancer->getConnection( DB_PRIMARY )->newReplaceQueryBuilder()
			->replaceInto( 'growthexperiments_link_recommendations' )
			->uniqueIndexFields( 'gelr_revision' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	public function insertNoLinkRecommendationFound( int $pageId, int $revisionId ): void {
		$row = [
			'gelr_revision' => $revisionId,
			'gelr_page' => $pageId,
			'gelr_data' => null,
		];
		$this->growthLoadBalancer->getConnection( DB_PRIMARY )->newReplaceQueryBuilder()
			->replaceInto( 'growthexperiments_link_recommendations' )
			->uniqueIndexFields( 'gelr_revision' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Delete all link recommendations for the given pages.
	 * @param int[] $pageIds
	 * @return int The number of deleted rows.
	 */
	public function deleteByPageIds( array $pageIds ): int {
		$dbw = $this->growthLoadBalancer->getConnection( DB_PRIMARY );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'growthexperiments_link_recommendations' )
			->where( [ 'gelr_page' => $pageIds ] )
			->caller( __METHOD__ )
			->execute();
		return $dbw->affectedRows();
	}

	/**
	 * Delete all link recommendations for the given page.
	 * @param LinkTarget $linkTarget
	 * @return bool
	 */
	public function deleteByLinkTarget( LinkTarget $linkTarget ): bool {
		$pageId = $this->titleFactory->newFromLinkTarget( $linkTarget )
			->getArticleID( IDBAccessObject::READ_LATEST );
		if ( $pageId === 0 ) {
			return false;
		}
		return (bool)$this->deleteByPageIds( [ $pageId ] );
	}

	// growthexperiments_link_submissions

	/**
	 * Get the list of link targets for a given page which should not be recommended anymore,
	 * as they have been rejected by users too many times.
	 * @param int $pageId
	 * @param int $limit Link targets rejected at least this many times are included.
	 * @return int[]
	 */
	public function getExcludedLinkIds( int $pageId, int $limit ): array {
		$pageIdsToExclude = $this->growthLoadBalancer->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->select( 'gels_target' )
			->from( 'growthexperiments_link_submissions' )
			->where( [ 'gels_page' => $pageId, 'gels_feedback' => 'r' ] )
			->groupBy( 'gels_target' )
			->having( "COUNT(*) >= $limit" )
			->caller( __METHOD__ )->fetchFieldValues();
		return array_map( 'intval', $pageIdsToExclude );
	}

	/**
	 * Record user feedback about a set for recommended links.
	 * Caller should make sure there is no feedback recorded for this revision yet.
	 * @param UserIdentity $user
	 * @param LinkRecommendation $linkRecommendation
	 * @param int[] $acceptedTargetIds Page IDs of accepted link targets.
	 * @param int[] $rejectedTargetIds Page IDs of rejected link targets.
	 * @param int[] $skippedTargetIds Page IDs of skipped link targets.
	 * @param int|null $editRevId Revision ID of the edit adding the links (might be null since
	 *   it's not necessary that any links have been added).
	 */
	public function recordSubmission(
		UserIdentity $user,
		LinkRecommendation $linkRecommendation,
		array $acceptedTargetIds,
		array $rejectedTargetIds,
		array $skippedTargetIds,
		?int $editRevId
	): void {
		$pageId = $linkRecommendation->getPageId();
		$revId = $linkRecommendation->getRevisionId();
		$links = $linkRecommendation->getLinks();
		$allTargetIds = [ 'a' => $acceptedTargetIds, 'r' => $rejectedTargetIds, 's' => $skippedTargetIds ];

		// correlate LinkRecommendation link data with the target IDs
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$linkIndexToTitleText = [];
		foreach ( $links as $i => $link ) {
			$title = $this->titleFactory->newFromTextThrow( $link->getLinkTarget() );
			$linkIndexToTitleText[$i] = $title->getPrefixedDBkey();
			$linkBatch->addObj( $title );
		}
		$titleTextToLinkIndex = array_flip( $linkIndexToTitleText );
		$titleTextToPageId = $linkBatch->execute();
		$pageIdToTitleText = array_flip( $titleTextToPageId );
		$pageIdToLink = [];
		foreach ( array_merge( ...array_values( $allTargetIds ) ) as $targetId ) {
			$titleText = $pageIdToTitleText[$targetId] ?? null;
			if ( $titleText === null ) {
				// User-submitted page ID does not exist. Could be some kind of race condition.
				Util::logException( new RuntimeException( 'Page ID does not exist ' ), [
					'pageID' => $targetId,
				] );
				continue;
			}
			$pageIdToLink[$targetId] = $links[$titleTextToLinkIndex[$titleText]];
		}

		$rowData = [
			'gels_page' => $pageId,
			'gels_revision' => $revId,
			'gels_edit_revision' => $editRevId,
			'gels_user' => $user->getId(),
		];
		$rows = [];
		foreach ( $allTargetIds as $feedback => $targetIds ) {
			foreach ( $targetIds as $targetId ) {
				$link = $pageIdToLink[$targetId] ?? null;
				if ( !$link ) {
					continue;
				}
				$rows[] = $rowData + [
					'gels_target' => $targetId,
					'gels_feedback' => $feedback,
					'gels_anchor_offset' => $link->getWikitextOffset(),
					'gels_anchor_length' => mb_strlen( $link->getText(), 'UTF-8' ),
				];
			}
		}
		if ( $rows ) {
			$this->growthLoadBalancer->getConnection( DB_PRIMARY )->newInsertQueryBuilder()
				->insertInto( 'growthexperiments_link_submissions' )
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Check if there is already a submission for a given recommendation.
	 * @param LinkRecommendation $linkRecommendation
	 * @param int $flags IDBAccessObject flags
	 * @return bool
	 */
	public function hasSubmission( LinkRecommendation $linkRecommendation, int $flags ): bool {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) === IDBAccessObject::READ_LATEST ) {
			$db = $this->getGrowthDB( DB_PRIMARY );
		} else {
			$db = $this->getGrowthDB( DB_REPLICA );
		}
		return (bool)$db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'growthexperiments_link_submissions' )
			->where( [ 'gels_revision' => $linkRecommendation->getRevisionId() ] )
			->caller( __METHOD__ )->fetchRowCount();
	}

	// common

	/**
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 * @return IDatabase
	 */
	public function getGrowthDB( int $index ): IDatabase {
		return $this->growthLoadBalancer->getConnection( $index );
	}

	/**
	 * Convert growthexperiments_link_recommendations rows into objects.
	 * Rows with no matching page are skipped.
	 * @param stdClass[] $rows
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation[]
	 */
	private function getLinkRecommendationsFromRows( array $rows, int $flags = 0 ): array {
		if ( !$rows ) {
			return [];
		}

		$pageIds = $linkTargets = [];
		foreach ( $rows as $row ) {
			$pageIds[] = $row->gelr_page;
		}

		$pageRecords = $this->pageStore
			->newSelectQueryBuilder( $flags )
			->wherePageIds( $pageIds )
			->caller( __METHOD__ )
			->fetchPageRecords();

		/** @var PageRecord $pageRecord */
		foreach ( $pageRecords as $pageRecord ) {
			$linkTarget = TitleValue::castPageToLinkTarget( $pageRecord );
			$linkTargets[$pageRecord->getId()] = $linkTarget;
		}

		$linkRecommendations = [];
		foreach ( $rows as $row ) {
			if ( $row->gelr_data === null ) {
				throw new \InvalidArgumentException( __METHOD__ . ' called with row with null data' );
			}
			// TODO use JSON_THROW_ON_ERROR once we require PHP 7.3
			$data = json_decode( $row->gelr_data, true );
			if ( $data === null ) {
				throw new DomainException( 'Invalid JSON: ' . json_last_error_msg() );
			}
			$linkTarget = $linkTargets[$row->gelr_page] ?? null;
			if ( !$linkTarget ) {
				continue;
			}

			$linkRecommendations[] = new LinkRecommendation(
				$linkTarget,
				/**
				 * Some DB drivers always return data as strings, regardless of column type.
				 * Thus casting to int is necessary.
				 * See {@link https://bugs.php.net/bug.php?id=44341}
				 */
				(int)$row->gelr_page,
				(int)$row->gelr_revision,
				LinkRecommendation::getLinksFromArray( $data['links'] ),
				// Backwards compatibility for recommendations added before metadata was included in output and stored.
				LinkRecommendation::getMetadataFromArray( $data['meta'] ?? [] )
			);
		}
		return $linkRecommendations;
	}

	/**
	 * @return (LinkRecommendation|NullLinkRecommendation)[]
	 */
	private function getAllRecommendationsFromAllRows( array $rows ): array {
		$existingRecommendationsData = [];
		$unavailableLinkRecommendations = [];
		foreach ( $rows as $row ) {
			if ( $row->gelr_data !== null ) {
				$existingRecommendationsData[] = $row;
			} else {
				$unavailableLinkRecommendations[] = new NullLinkRecommendation(
					(int)$row->gelr_page,
					(int)$row->gelr_revision,
				);
			}
		}
		$existingLinkRecommendations = $this->getLinkRecommendationsFromRows( $existingRecommendationsData );

		return array_merge( $existingLinkRecommendations, $unavailableLinkRecommendations );
	}

	/**
	 * @param int $pageId
	 * @param LinkTarget[] $excludedTemplates
	 * @return int
	 */
	public function getNumberOfExcludedTemplatesOnPage( int $pageId, array $excludedTemplates ): int {
		if ( $excludedTemplates === [] ) {
			return 0;
		}
		$dbr = $this->connectionProvider->getReplicaDatabase( TemplateLinksTable::VIRTUAL_DOMAIN );
		return $dbr->newSelectQueryBuilder()
			->select( 'lt_id' )
			->from( 'templatelinks' )
			->join( 'linktarget', null, [ 'tl_target_id = lt_id' ] )
			->where( [
				'tl_from' => $pageId,
				'lt_namespace' => 10,
				'lt_title IN (' . $dbr->makeList(
					array_map(
						static fn ( LinkTarget $linkTarget ): string => $linkTarget->getDBkey(),
						$excludedTemplates,
					)
				) . ')',
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

}
