<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use CirrusSearch\CirrusSearchServices;
use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\CommunityConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\PageRecord;
use MediaWiki\Page\PageStore;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\WikiMap\WikiMap;
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

	private LinkRecommendationStore $linkRecommendationStore;
	private WeightedTagsUpdater $weightedTagsUpdater;
	private LinkBatchFactory $linkBatchFactory;
	private PageStore $pageStore;
	private TitleFormatter $titleFormatter;
	private ?int $randomSeed;

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
		$this->addOption(
			'verbose',
			'Show debug output. (can be provided multiple times)',
			// required
			false,
			// has arg
			false,
			'v',
			// multiple occurrences allowed
			true
		);
		$this->addOption( 'force', 'Force the script to run in production (use with care)' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute(): void {
		$this->init();
		if ( !$this->hasOption( 'search-index' ) && !$this->hasOption( 'db-table' ) ) {
			$this->fatalError( 'At least one of --search-index and --db-table must be specified.' );
		}
		if ( $this->hasOption( 'search-index' ) ) {
			$this->verboseOutput( "Removing search index entries not found or outdated in the database...\n" );
			$this->fixSearchIndex();
		}
		if ( $this->hasOption( 'db-table' ) ) {
			$this->verboseOutput( "Removing database entries not found in the search index...\n" );
			$this->fixDatabaseTable();
		}
	}

	public function init(): void {
		$services = $this->getServiceContainer();
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
		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();

		if (
			$configurationLoader instanceof CommunityConfigurationLoader &&
			$services->getMainConfig()->get( 'GENewcomerTasksLinkRecommendationsEnabled' )
		) {
			// Pretend link-recommendation is enabled (T371316)
			// Task suggester is not be adapted to query disabled task types.
			$configurationLoader->enableTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}

		$this->linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$this->weightedTagsUpdater = CirrusSearchServices::wrap( $services )->getWeightedTagsUpdater();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->pageStore = $services->getPageStore();
		$this->titleFormatter = $services->getTitleFormatter();

		$taskTypes = $configurationLoader->getTaskTypes();
		$linkRecommendationTaskType = $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
		if ( !$linkRecommendationTaskType instanceof LinkRecommendationTaskType ) {
			$wiki = WikiMap::getCurrentWikiId();
			$type = get_debug_type( $linkRecommendationTaskType );
			$this->fatalError( "$wiki: '$type' is not a link recommendation task type" );
		}
	}

	private function fixSearchIndex(): void {
		$fixing = $this->hasOption( 'dry-run' ) ? 'Would fix' : 'Fixing';
		$batchSize = $this->getBatchSize();
		$randomize = $this->getOption( 'random', false );
		$fixedCount = 0;
		$okCount = 0;
		$pageIdsFixed = [];

		$oresTopics = array_keys( ArticleTopicFeature::TERMS_TO_LABELS );
		// Search offsets are limited to 10K. Search topic by topic. This is still not a 100%
		// guarantee that we'll avoid a >10K result set, but it's the best we can do.
		foreach ( $oresTopics as $oresTopic ) {
			$from = 0;
			$this->verboseOutput( "  checking topic $oresTopic...\n" );
			$topicFixedCount = 0;
			$topicOKCount = 0;
			$topicTotalCount = 0;
			$searchQuery = "hasrecommendation:link articletopic:$oresTopic";
			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			while ( $titles = $this->search( $searchQuery, $batchSize, $from, $randomize ) ) {
				$topicTotalCount += count( $titles );
				$this->verboseOutput( '    checking ' . count( $titles ) . " titles... ($topicTotalCount so far)\n" );
				$pageIdsToCheck = $this->titlesToPageIds( $titles );
				$pageIdsToFix = array_diff( $pageIdsToCheck,
					$this->linkRecommendationStore->filterPageIds( $pageIdsToCheck ) );
				$pageIdsToFix = array_diff( $pageIdsToFix, $pageIdsFixed );
				$pagesToFix = $this->pageIdsToPageRecords( $pageIdsToFix );

				foreach ( $pagesToFix as $pageRecord ) {
					$this->verboseOutput(
						"    $fixing " . $this->titleFormatter->getPrefixedText( $pageRecord ) . "\n",
						2
					);
					if ( !$this->hasOption( 'dry-run' ) ) {
						$this->weightedTagsUpdater->resetWeightedTags( $pageRecord, [ 'recommendation.link' ] );
					}
					$pageIdsFixed[] = $pageRecord->getId();
				}
				$from = min( 10000, $batchSize + $from );
				$fixedCount += count( $pagesToFix );
				$topicFixedCount += count( $pagesToFix );
				$okCount += count( $pageIdsToCheck ) - count( $pagesToFix );
				$topicOKCount += count( $pageIdsToCheck ) - count( $pagesToFix );
				if ( $batchSize + $from > 10000 ) {
					$this->error( "  topic $oresTopic had more than 10K tasks" );
					break;
				}
			}
			$this->verboseOutput( "  topic $oresTopic: $topicOKCount OK, $topicFixedCount fixed/fixable\n" );
		}

		// phpcs:ignore Generic.Files.LineLength
		$this->output( "Total number of OK search index entries: $okCount\n (results in multiple topics counted multiple times)\n" );
		$this->maybeReportFixedCount( $fixedCount, 'search-index' );
	}

	private function fixDatabaseTable(): void {
		$fixing = $this->hasOption( 'dry-run' ) ? 'Would fix' : 'Fixing';
		$from = null;
		$fixedCount = 0;
		$okCount = 0;
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $pageIds = $this->linkRecommendationStore->listPageIds( $this->getBatchSize(), $from ) ) {
			$this->verboseOutput( '  checking ' . count( $pageIds ) . " titles...\n" );
			$titlesToFix = $this->search( '-hasrecommendation:link pageid:' . implode( '|', $pageIds ),
				$this->getBatchSize(), 0 );
			$pageIdsToFix = $this->titlesToPageIds( $titlesToFix );
			foreach ( $titlesToFix as $title ) {
				$this->verboseOutput( "    $fixing " . $title->getPrefixedText() . "\n", 2 );
			}
			if ( $pageIdsToFix && !$this->hasOption( 'dry-run' ) ) {
				$this->beginTransaction( $this->linkRecommendationStore->getGrowthDB( DB_PRIMARY ), __METHOD__ );
				$this->linkRecommendationStore->deleteByPageIds( $pageIdsToFix );
				$this->commitTransaction( $this->linkRecommendationStore->getGrowthDB( DB_PRIMARY ), __METHOD__ );
			}
			$from = end( $pageIds );
			$fixedCount += count( $pageIdsToFix );
			$okCount += count( $pageIds ) - count( $pageIdsToFix );
		}
		$this->output( "Total number of OK db-table entries: $okCount\n" );
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
		$searchEngine = $this->getServiceContainer()->newSearchEngine();
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

	private function verboseOutput( string $output, int $messageVerbosity = 1 ): void {
		if ( !$this->hasOption( 'verbose' ) ) {
			return;
		}

		$levelOfVerbosity = count( $this->getOption( 'verbose' ) );

		if ( $levelOfVerbosity >= $messageVerbosity ) {
			$this->output( $output );
		}
	}

	private function maybeReportFixedCount( int $count, string $type ): void {
		$this->output( "Total number of dangling $type entries: $count\n" );
		if ( !$this->hasOption( 'statsd' ) ) {
			return;
		}
		$fixWord = $this->hasOption( 'dry-run' ) ? 'fixable' : 'fixed';

		$wiki = WikiMap::getCurrentWikiId();
		$statsFactory = $this->getServiceContainer()->getStatsFactory();
		$statsFactory
			->withComponent( 'GrowthExperiments' )
			->getCounter( 'link_recommendation_dangling_entries' )
			->setLabel( 'type', $type )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'fix_word', $fixWord )
			->copyToStatsdAt( "$wiki.growthexperiments.$fixWord.link-recommendation.$type" )
			->incrementBy( $count );
	}

}

$maintClass = FixLinkRecommendationData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
