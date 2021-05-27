<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use DBAccessObjectUtils;
use DomainException;
use GrowthExperiments\Util;
use IDBAccessObject;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use RuntimeException;
use TitleFactory;
use Wikimedia\Rdbms\IDatabase;

/**
 * Service that handles access to the link recommendation related database tables.
 */
class LinkRecommendationStore {

	/** @var IDatabase Read handle */
	private $dbr;

	/** @var IDatabase Write handle */
	private $dbw;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param IDatabase $dbr
	 * @param IDatabase $dbw
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 */
	public function __construct(
		IDatabase $dbr,
		IDatabase $dbw,
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->dbr = $dbr;
		$this->dbw = $dbw;
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	// growthexperiments_link_recommendations

	/**
	 * Get a link recommendation by revision ID.
	 * @param int $revId
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	public function getByRevId( int $revId, int $flags = 0 ): ?LinkRecommendation {
		[ $index, $options ] = DBAccessObjectUtils::getDBOptions( $flags );
		$row = $this->getDB( $index )->selectRow(
			'growthexperiments_link_recommendations',
			[ 'gelr_page', 'gelr_data' ],
			[ 'gelr_revision' => $revId ],
			__METHOD__,
			$options
		);
		if ( $row === false ) {
			return null;
		}
		// TODO use JSON_THROW_ON_ERROR once we require PHP 7.3
		$data = json_decode( $row->gelr_data, true );
		if ( $data === null ) {
			throw new DomainException( 'Invalid JSON: ' . json_last_error_msg() );
		}
		$title = $this->titleFactory->newFromID( $row->gelr_page, $flags );
		if ( !$title ) {
			return null;
		}

		return new LinkRecommendation(
			$title,
			$row->gelr_page,
			$revId,
			LinkRecommendation::getLinksFromArray( $data['links'] ),
			// Backwards compatibility for recommendations added before metadata was included in output and stored.
			LinkRecommendation::getMetadataFromArray( $data['meta'] ?? [] )
		);
	}

	/**
	 * Get a link recommendation by link target.
	 * @param LinkTarget $linkTarget
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	public function getByLinkTarget( LinkTarget $linkTarget, int $flags = 0 ): ?LinkRecommendation {
		$revId = $this->titleFactory->newFromLinkTarget( $linkTarget )->getLatestRevID( $flags );
		if ( $revId === 0 ) {
			return null;
		}
		return $this->getByRevId( $revId, $flags );
	}

	/**
	 * Given a set of page IDs, return the ones which have a valid link recommendation
	 * (valid as in it's for the latest revision).
	 * @param int[] $pageIds
	 * @return int[]
	 */
	public function filterPageIds( array $pageIds ): array {
		$titles = $this->titleFactory->newFromIDs( $pageIds );
		$conds = [];
		foreach ( $titles as $title ) {
			// Making it obvious there's no SQL injection risk is nice, but Phan disagrees.
			// @phan-suppress-next-line PhanRedundantConditionInLoop
			$pageId = (int)$title->getId();
			$revId = (int)$title->getLatestRevID();
			if ( !$pageId || !$revId ) {
				continue;
			}
			// $revId can be outdated due to replag; we don't want to delete the record then.
			$conds[] = "gelr_page = $pageId AND gelr_revision >= $revId";
		}
		return array_map( 'intval', $this->dbr->selectFieldValues(
			'growthexperiments_link_recommendations',
			'gelr_page',
			$this->dbr->makeList( $conds, IDatabase::LIST_OR ),
			__METHOD__
		) );
	}

	/**
	 * List all pages with link recommendations, by page ID.
	 * @param int $limit
	 * @param int|null $from ID to list from, exclusive
	 * @return int[]
	 */
	public function listPageIds( int $limit, int $from = null ): array {
		return array_map( 'intval', $this->dbr->selectFieldValues(
			'growthexperiments_link_recommendations',
			'gelr_page',
			$from ? [ "gelr_page > $from" ] : [],
			__METHOD__,
			[
				'LIMIT' => $limit,
				'GROUP BY' => 'gelr_page',
				'ORDER BY' => 'gelr_page ASC',
			]
		) );
	}

	/**
	 * Insert a new link recommendation.
	 * @param LinkRecommendation $linkRecommendation
	 */
	public function insert( LinkRecommendation $linkRecommendation ): void {
		$pageId = $linkRecommendation->getPageId();
		$revisionId = $linkRecommendation->getRevisionId();
		$row = [
			'gelr_revision' => $revisionId,
			'gelr_page' => $pageId,
			'gelr_data' => json_encode( $linkRecommendation->toArray() ),
		];
		$this->dbw->upsert(
			'growthexperiments_link_recommendations',
			$row,
			'gelr_revision',
			$row,
			__METHOD__
		);
	}

	/**
	 * Delete all link recommendations for the given pages.
	 * @param int[] $pageIds
	 * @return int The number of deleted rows.
	 */
	public function deleteByPageIds( array $pageIds ): int {
		$this->dbw->delete(
			'growthexperiments_link_recommendations',
			[ 'gelr_page' => $pageIds ],
			__METHOD__
		);
		return $this->dbw->affectedRows();
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
		$pageIdsToExclude = $this->dbr->selectFieldValues(
			'growthexperiments_link_submissions',
			'gels_target',
			[
				'gels_page' => $pageId,
				'gels_feedback' => 'r',
			],
			__METHOD__,
			[
				'GROUP BY' => 'gels_target',
				'HAVING' => "COUNT(*) >= $limit",
			]
		);
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
				Util::logError( new RuntimeException( 'Page ID does not exist ' ), [
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
					'gels_anchor_length' => (int)mb_strlen( $link->getText(), 'UTF-8' ),
				];
			}
		}
		// No need to check if $rows is empty, Database::insert() does that.
		$this->dbw->insert(
			'growthexperiments_link_submissions',
			$rows,
			__METHOD__
		);
	}

	/**
	 * Check if there is already a submission for a given recommendation.
	 * @param LinkRecommendation $linkRecommendation
	 * @param int $flags IDBAccessObject flags
	 * @return bool
	 */
	public function hasSubmission( LinkRecommendation $linkRecommendation, int $flags ): bool {
		[ $index, $options ] = DBAccessObjectUtils::getDBOptions( $flags );
		return (bool)$this->getDB( $index )->selectRowCount(
			'growthexperiments_link_submissions',
			'*',
			[ 'gels_revision' => $linkRecommendation->getRevisionId() ],
			__METHOD__,
			$options
		);
	}

	// common

	/**
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 * @return IDatabase
	 */
	public function getDB( int $index ): IDatabase {
		return ( $index === DB_PRIMARY ) ? $this->dbw : $this->dbr;
	}

}
