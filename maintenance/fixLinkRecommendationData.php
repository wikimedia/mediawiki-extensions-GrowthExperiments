<?php

namespace GrowthExperiments\Maintenance;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Query\ArticleTopicFeature;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use Maintenance;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageRecord;
use MediaWiki\Page\PageStore;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use StatusValue;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Aligns link recommendation data in the growthexperiments_link_recommendations table and the
 * search index. Useful for fixing test setups if the DB or the index gets messed up somehow.
 *
 * No attempt is made to handle delayed search index updates due to job queue
 * size. As such, the script is risky for production, and needs to be used with care.
 */
class FixLinkRecommendationData extends Maintenance {

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/** @var CirrusSearch */
	private $cirrusSearch;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var PageStore */
	private $pageStore;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var int|null */
	private $randomSeed;

	/** @var LinkRecommendationTaskType */
	private $linkRecommendationTaskType;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CirrusSearch' );

		$this->addDescription( 'Aligns link recommendation data in the '
			. 'growthexperiments_link_recommendations table and the search index, by deleting table rows '
			. 'without a matching search index entry and/or search index entries without a matching table row.' );
		$this->addOption( 'search-index', 'Delete search index entries which do not match the DB table. '
			. '(Note that this relies on the job queue to work.)' );
		$this->addOption( 'random', 'Sort randomly. Applies to --search-index only. '
			. 'This is mainly useful with --statsd.' );
		$this->addOption( 'db-table', 'Delete DB table entries which do not match the search index.' );
		$this->addOption( 'dry-run', 'Run without making any changes.' );
		$this->addOption( 'statsd', 'Report the number of fixes (or would-be fixes, '
			. 'when called with --dry-run) to statsd' );
		$this->addOption( 'verbose', 'Show debug output.' );
		$this->addOption( 'force', 'Force the script to run in production (use with care)' );
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
		if ( $this->hasOption( 'db-table' )
			&& !$this->hasOption( 'dry-run' )
			&& !$growthServices->getGrowthConfig()->get( 'GEDeveloperSetup' )
			&& !$this->hasOption( 'force' )
		) {
			// Adding search index entries is batched in production, and takes hours. This script would delete
			// the associated DB records in the meantime.
			$this->fatalError( 'The --db-table option cannot be safely run in production. (If the current '
				. 'environment is not production, $wgGEDeveloperSetup should be set to true. If you REALLY '
				. 'know what you are doing, use --force.)' );
		}
		$this->configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$this->linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$this->cirrusSearch = new CirrusSearch();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->pageStore = $services->getPageStore();
		$this->titleFormatter = $services->getTitleFormatter();

		$taskTypes = $this->configurationLoader->getTaskTypes();
		$linkRecommendationTaskType = $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
		if ( !$linkRecommendationTaskType instanceof LinkRecommendationTaskType ) {
			$this->fatalError( sprintf( "'%s' is not a link recommendation task type",
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ) );
		} else {
			$this->linkRecommendationTaskType = $linkRecommendationTaskType;
		}
	}

	private function fixSearchIndex() {
		$fixing = $this->hasOption( 'dry-run' ) ? 'Would fix' : 'Fixing';
		$batchSize = $this->getBatchSize();
		$randomize = $this->getOption( 'random', false );
		$fixedCount = 0;
		$pageIdsFixed = [];

		$oresTopics = array_keys( ArticleTopicFeature::TERMS_TO_LABELS );
		// Search offsets are limited to 10K. Search topic by topic. This is still not a 100%
		// guarantee that we'll avoid a >10K result set, but it's the best we can do.
		foreach ( $oresTopics as $oresTopic ) {
			$from = 0;
			$this->verboseOutput( "  checking topic $oresTopic...\n" );
			$searchQuery = "hasrecommendation:link articletopic:$oresTopic";
			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			while ( $titles = $this->search( $searchQuery, $batchSize, $from, $randomize ) ) {
				$this->verboseOutput( '    checking ' . count( $titles ) . " titles...\n" );
				$pageIdsToCheck = $this->titlesToPageIds( $titles );
				$pageIdsToFix = array_diff( $pageIdsToCheck,
					$this->linkRecommendationStore->filterPageIds( $pageIdsToCheck ) );
				$pageIdsToFix = array_diff( $pageIdsToFix, $pageIdsFixed );
				$pagesToFix = $this->pageIdsToPageRecords( $pageIdsToFix );

				foreach ( $pagesToFix as $pageRecord ) {
					$this->verboseOutput(
						"    $fixing " . $this->titleFormatter->getPrefixedText( $pageRecord ) . "\n"
					);
					if ( !$this->hasOption( 'dry-run' ) ) {
						$this->cirrusSearch->resetWeightedTags( $pageRecord, 'recommendation.link' );
					}
					$pageIdsFixed[] = $pageRecord->getId();
				}
				$from = min( 10000, $batchSize + $from );
				$fixedCount += count( $pagesToFix );
				if ( $batchSize + $from > 10000 ) {
					$this->error( "  topic $oresTopic had more than 10K tasks" );
					break;
				}
			}
		}
		$this->maybeReportFixedCount( $fixedCount, 'search-index' );
	}

	private function fixDatabaseTable() {
		$fixing = $this->hasOption( 'dry-run' ) ? 'Would fix' : 'Fixing';
		$from = null;
		$fixedCount = 0;
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
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
	 * @param bool $randomize Use random sorting
	 * @return Title[]
	 */
	private function search( string $query, int $limit, int $offset, bool $randomize = false ): array {
		$searchEngine = MediaWikiServices::getInstance()->newSearchEngine();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setShowSuggestion( false );
		if ( $randomize ) {
			$searchEngine->setFeatureData( 'random_seed', $this->getRandomSeed() );
			$searchEngine->setSort( 'random' );
		} else {
			// Sort by creation date as it's stable over time.
			$searchEngine->setSort( 'create_timestamp_asc' );
		}
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
	 * Helper method for a random value that remains the same during successive calls.
	 * @return int
	 */
	private function getRandomSeed(): int {
		if ( $this->randomSeed === null ) {
			$this->randomSeed = random_int( 0, PHP_INT_MAX );
		}
		return $this->randomSeed;
	}

	/**
	 * @param Title[] $titles
	 * @return list<int>
	 */
	private function titlesToPageIds( array $titles ): array {
		$linkBatch = $this->linkBatchFactory->newLinkBatch( $titles );
		return array_values( $linkBatch->execute() );
	}

	/**
	 * @param int[] $pageIds
	 * @return PageRecord[]
	 */
	private function pageIdsToPageRecords( array $pageIds ): array {
		$pageRecords = $this->pageStore
			->newSelectQueryBuilder()
			->wherePageIds( $pageIds )
			->caller( __METHOD__ )
			->fetchPageRecords();
		return iterator_to_array( $pageRecords );
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
