<?php

namespace GrowthExperiments\Maintenance;

use CirrusSearch\CirrusSearch;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use Maintenance;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\MediaWikiServices;
use Status;
use StatusValue;
use Title;
use TitleFactory;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

/**
 * Aligns link recommendation data in the growthexperiments_link_recommendations table and the
 * search index. Useful for fixing test setups if the DB or the index gets messed up somehow.
 *
 * No attempt is made to handle replication lag, delayed search index updates due to job queue
 * size or batching, and similar potential race conditions. As such, this script is not appropriate
 * for production use.
 */
class FixLinkRecommendationData extends Maintenance {

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/** @var CirrusSearch */
	private $cirrusSearch;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var TitleFactory */
	private $titleFactory;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CirrusSearch' );

		$this->addDescription( 'Aligns link recommendation data in the '
			. 'growthexperiments_link_recommendations table and the search index, by deleting table rows '
			. 'without a matching search index entry and/or search index entries without a matching table row.' );
		$this->addOption( 'search-index', 'Delete search index entries which do not match the DB table. '
			. '(Note that this relies on the job queue to work.)' );
		$this->addOption( 'db-table', 'Delete DB table entries which do not match the search index.' );
		$this->addOption( 'dry-run', 'Run without making any changes.' );
		$this->addOption( 'statsd', 'Report the number of fixes (or would-be fixes, '
			. 'when called with --dry-run) to statsd' );
		$this->addOption( 'verbose', 'Show debug output.' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		if ( !$this->hasOption( 'search-index' ) && !$this->hasOption( 'db-table' ) ) {
			$this->fatalError( 'At least one of --search-index and --db-table must be specified.' );
		}
		if ( $this->hasOption( 'search-index' ) ) {
			$this->verboseOutput( "Removing search index entries not found in the database...\n" );
			$this->fixSearchIndex();
		}
		if ( $this->hasOption( 'db-table' ) ) {
			$this->verboseOutput( "Removing database entries not found in the search index...\n" );
			$this->fixDatabaseTable();
		}
	}

	public function init() {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( !$growthServices->getConfig()->get( 'GEDeveloperSetup' ) && $this->hasOption( 'db-table' ) ) {
			// Adding search index entries is batched in production, and takes hours. This script would delete
			// the associated DB records in the meantime.
			$this->fatalError( 'The --db-table option cannot be safely run in production. (If the current '
				. 'environment is not production, $wgGEDeveloperSetup should be set to true.)' );
		}
		$this->linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$this->cirrusSearch = new CirrusSearch();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->titleFactory = $services->getTitleFactory();
	}

	private function fixSearchIndex() {
		$fixing = $this->hasOption( 'dry-run' ) ? 'Would fix' : 'Fixing';
		$from = 0;
		$fixedCount = 0;
		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
		while ( $titles = $this->search( 'hasrecommendation:link', $this->getBatchSize(), $from ) ) {
			$this->verboseOutput( '  checking ' . count( $titles ) . " titles...\n" );
			$pageIdsToCheck = $this->titlesToPageIds( $titles );
			$pageIdsToFix = array_diff( $pageIdsToCheck,
				$this->linkRecommendationStore->filterPageIds( $pageIdsToCheck ) );
			$titlesToFix = $this->pageIdsToTitles( $pageIdsToFix );
			foreach ( $titlesToFix as $title ) {
				$this->verboseOutput( "    $fixing " . $title->getPrefixedText() . "\n" );
				if ( !$this->hasOption( 'dry-run' ) ) {
					$this->cirrusSearch->resetWeightedTags( $title->toPageIdentity(), 'recommendation.link' );
				}
			}
			$from += $this->getBatchSize();
			$fixedCount += count( $titlesToFix );
		}
		$this->maybeReportFixedCount( $fixedCount, 'search-index' );
	}

	private function fixDatabaseTable() {
		$fixing = $this->hasOption( 'dry-run' ) ? 'Would fix' : 'Fixing';
		$from = null;
		$fixedCount = 0;
		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
		while ( $pageIds = $this->linkRecommendationStore->listPageIds( $this->getBatchSize(), $from ) ) {
			$this->verboseOutput( '  checking ' . count( $pageIds ) . " titles...\n" );
			$titlesToFix = $this->search( '-hasrecommendation:link pageid:' . implode( '|', $pageIds ),
				$this->getBatchSize(), 0 );
			$pageIdsToFix = $this->titlesToPageIds( $titlesToFix );
			foreach ( $titlesToFix as $title ) {
				$this->verboseOutput( "    $fixing " . $title->getPrefixedText() . "\n" );
			}
			if ( $pageIdsToFix && !$this->hasOption( 'dry-run' ) ) {
				$this->linkRecommendationStore->deleteByPageIds( $pageIdsToFix );
				$this->commitTransaction( $this->linkRecommendationStore->getDB( DB_PRIMARY ), __METHOD__ );
			}
			$from = end( $pageIds );
			$fixedCount += count( $pageIdsToFix );
		}
		$this->maybeReportFixedCount( $fixedCount, 'db-table' );
	}

	/**
	 * Do a CirrusSearch query.
	 * @param string $query Search query
	 * @param int $limit
	 * @param int $offset
	 * @return Title[]
	 */
	private function search( string $query, int $limit, int $offset ): array {
		$searchEngine = MediaWikiServices::getInstance()->newSearchEngine();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setShowSuggestion( false );
		// Sort by creation date as it's stable over time.
		$searchEngine->setSort( 'create_timestamp_asc' );
		$matches = $searchEngine->searchText( $query )
			?? StatusValue::newFatal( 'rawmessage', 'Search is disabled' );
		if ( $matches instanceof StatusValue ) {
			if ( $matches->isOK() ) {
				$matches = $matches->getValue();
			} else {
				$this->fatalError( Status::wrap( $matches )->getWikiText( false, false, 'en' ) );
			}
		}
		return $matches->extractTitles();
	}

	/**
	 * @param Title[] $titles
	 * @return int[]
	 */
	private function titlesToPageIds( array $titles ): array {
		$linkBatch = $this->linkBatchFactory->newLinkBatch( $titles );
		return $linkBatch->execute();
	}

	/**
	 * @param int[] $pageIds
	 * @return Title[]
	 */
	private function pageIdsToTitles( array $pageIds ): array {
		return $this->titleFactory->newFromIDs( $pageIds );
	}

	private function verboseOutput( string $output ): void {
		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( $output );
		}
	}

	private function maybeReportFixedCount( int $count, string $type ) {
		if ( !$this->hasOption( 'statsd' ) ) {
			return;
		}
		$fixWord = $this->hasOption( 'dry-run' ) ? 'fixable' : 'fixed';
		$dataFactory = MediaWikiServices::getInstance()->getPerDbNameStatsdDataFactory();
		$dataFactory->updateCount( "growthexperiments.$fixWord.link-recommendation.$type", $count );
	}

}

$maintClass = FixLinkRecommendationData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
