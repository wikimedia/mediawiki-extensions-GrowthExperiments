<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use DBAccessObjectUtils;
use DomainException;
use IDBAccessObject;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
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

	/**
	 * @param IDatabase $dbr
	 * @param IDatabase $dbw
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( IDatabase $dbr, IDatabase $dbw, TitleFactory $titleFactory ) {
		$this->dbr = $dbr;
		$this->dbw = $dbw;
		$this->titleFactory = $titleFactory;
	}

	// growthexperiments_link_recommendations

	/**
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

		$links = LinkRecommendation::getLinksFromArray( $data['links'] );
		return new LinkRecommendation( $title, $row->gelr_page, $revId, $links );
	}

	/**
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
	 * @param int $pageId
	 * @return bool Whether anything was deleted.
	 */
	public function deleteByPageId( int $pageId ): bool {
		$this->dbw->delete(
			'growthexperiments_link_recommendations',
			[ 'gelr_page' => $pageId ],
			__METHOD__
		);
		return (bool)$this->dbw->affectedRows();
	}

	/**
	 * @param LinkTarget $linkTarget
	 * @return bool
	 */
	public function deleteByLinkTarget( LinkTarget $linkTarget ): bool {
		$pageId = $this->titleFactory->newFromLinkTarget( $linkTarget )
			->getArticleID( IDBAccessObject::READ_LATEST );
		if ( $pageId === 0 ) {
			return false;
		}
		return $this->deleteByPageId( $pageId );
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

		$rowData = [
			'gels_page' => $pageId,
			'gels_revision' => $revId,
			'gels_edit_revision' => $editRevId,
			'gels_user' => $user->getId(),
		];
		$allTargetIds = [ 'a' => $acceptedTargetIds, 'r' => $rejectedTargetIds, 's' => $skippedTargetIds ];

		$rows = [];
		foreach ( $allTargetIds as $feedback => $targetIds ) {
			foreach ( $targetIds as $targetId ) {
				$rows[] = $rowData + [ 'gels_target' => $targetId, 'gels_feedback' => $feedback ];
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
	 * @param int $index DB_MASTER or DB_SLAVE
	 * @return IDatabase
	 */
	private function getDB( int $index ): IDatabase {
		return ( $index === DB_MASTER ) ? $this->dbw : $this->dbr;
	}

}
