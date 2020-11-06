<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use DBAccessObjectUtils;
use DomainException;
use IDBAccessObject;
use MediaWiki\Linker\LinkTarget;
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

	/**
	 * @param int $pageId
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	public function getByPageId( int $pageId, int $flags = 0 ): ?LinkRecommendation {
		[ $index, $options ] = DBAccessObjectUtils::getDBOptions( $flags );
		$row = $this->getDB( $index )->selectRow(
			'growthexperiments_link_recommendations',
			[ 'gelr_revision', 'gelr_data' ],
			[ 'gelr_page' => $pageId ],
			__METHOD__,
			$options
		);
		if ( $row === false ) {
			return null;
		}
		// TODO use JSON_THROW_ON_ERROR once we require PHP 7.3
		$data = json_decode( $row->gelr_data );
		if ( $data === null ) {
			throw new DomainException( 'Invalid JSON: ' . json_last_error_msg() );
		}
		$title = $this->titleFactory->newFromID( $pageId, $flags );
		if ( !$title ) {
			return null;
		}

		return new LinkRecommendation( $title, $pageId, $row->gelr_revision, $data );
	}

	/**
	 * @param LinkTarget $linkTarget
	 * @param int $flags IDBAccessObject flags
	 * @return LinkRecommendation|null
	 */
	public function getByLinkTarget( LinkTarget $linkTarget, int $flags = 0 ): ?LinkRecommendation {
		$pageId = $this->titleFactory->newFromLinkTarget( $linkTarget )->getArticleID( $flags );
		if ( $pageId === 0 ) {
			return null;
		}
		return $this->getByPageId( $pageId, $flags );
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

	/**
	 * @param int $index DB_MASTER or DB_SLAVE
	 * @return IDatabase
	 */
	private function getDB( int $index ): IDatabase {
		return ( $index === DB_MASTER ) ? $this->dbw : $this->dbr;
	}

}
