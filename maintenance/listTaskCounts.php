<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\CachedSuggestionsInfo;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\WikiMap\WikiMap;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * List the number of tasks available for each topic
 */
class ListTaskCounts extends Maintenance {

	/** @var string 'growth' or 'ores' */
	private string $topicType;
	private TopicDecorator $taskTypesAndTopics;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'List the number of tasks available for each topic.' );
		$this->addOption( 'tasktype', 'Task types to query, specify multiple times for multiple ' .
									  'task types. Defaults to all task types.', false, true, false, true );
		$this->addOption( 'topic', 'Topics to query, specify multiple times for multiple ' .
									  'topics. Defaults to all topics.', false, true, false, true );
		$this->addOption( 'topictype', "Topic type to use ('ores' or 'growth').", false, true );
		$this->addOption( 'statsd', 'Send topic counts to statsd. For link recommendations only.' );
		$this->addOption( 'output', "'ascii-table' (default), 'json' or 'none'", false, true );
	}

	/** @inheritDoc
	 * @throws MaintenanceFatalError
	 */
	public function execute() {
		if ( $this->getConfig()->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$this->output( "Local tasks disabled\n" );
			return;
		}

		$this->topicType = $this->getOption( 'topictype', 'growth' );
		if ( !in_array( $this->topicType, [ 'ores', 'growth' ], true ) ) {
			$this->fatalError( 'topictype must be one of: growth, ores' );
		}

		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$newcomerTaskConfigurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$this->taskTypesAndTopics = new TopicDecorator(
			$newcomerTaskConfigurationLoader,
			$growthServices->getTopicRegistry(),
			$this->topicType == 'ores'
		);

		$taskTypes = $this->getTaskTypes();
		$topics = $this->getTopics();
		[ $taskCounts, $taskTypeCounts, $topicCounts ] = $this->getStats( $taskTypes, $topics );
		if ( $this->hasOption( 'statsd' ) ) {
			$this->reportTaskCounts( $taskCounts, $taskTypeCounts );
		}
		$this->printResults( $taskTypes, $topics, $taskCounts, $taskTypeCounts, $topicCounts );
	}

	/**
	 * Get task types to list task counts for
	 *
	 * @return string[] task type ID list
	 * @throws MaintenanceFatalError
	 */
	private function getTaskTypes(): array {
		$allTaskTypes = array_keys( $this->taskTypesAndTopics->getTaskTypes() );
		$taskTypes = $this->getOption( 'tasktype', $allTaskTypes );
		if ( array_diff( $taskTypes, $allTaskTypes ) ) {
			$this->fatalError( 'Invalid task types: ' . implode( ', ', array_diff( $taskTypes, $allTaskTypes ) ) );
		}

		return $taskTypes;
	}

	/**
	 * Get topics to list task counts for
	 *
	 * @return string[] topic ID list
	 * @throws MaintenanceFatalError
	 */
	private function getTopics(): array {
		$allTopics = array_keys( $this->taskTypesAndTopics->getTopics() );
		$topics = $this->getOption( 'topic', $allTopics );
		if ( array_diff( $topics, $allTopics ) ) {
			$this->fatalError( 'Invalid topics: ' . implode( ', ', array_diff( $topics, $allTopics ) ) );
		}
		return $topics;
	}

	/**
	 * @param string[] $taskTypes List of task type IDs to count for
	 * @param string[] $topics List of topic IDs to count for
	 * @return array{0:int[][],1:int[],2:int[]} An array with three elements:
	 *   - a matrix of task type ID => topic ID => count
	 *   - a list of task type ID => total count
	 *   - a list of topic ID => total count.
	 *   Note that the second and third elements are not the same as column and row totals
	 *   of the first element, because an article can have multiple topics and multiple
	 *   task types, and not all articles have task types, and some (the recently created)
	 *   might not even have topics.
	 */
	private function getStats( $taskTypes, $topics ): array {
		$taskCounts = $taskTypeCounts = $topicCounts = [];
		$mwServices = $this->getServiceContainer();
		$services = GrowthExperimentsServices::wrap( $mwServices );
		// Cache stats for Growth topics since they're also used in SpecialNewcomerTasksInfo
		$shouldCacheStats = $this->topicType === 'growth';
		$suggestionsInfoService = new SuggestionsInfo(
			$services->getTaskSuggesterFactory(),
			$services->getTaskTypeHandlerRegistry(),
			$this->taskTypesAndTopics,
			$this->taskTypesAndTopics
		);
		if ( $shouldCacheStats ) {
			$suggestionsInfo = new CachedSuggestionsInfo(
				$suggestionsInfoService,
				$mwServices->getMainWANObjectCache()
			);
		} else {
			$suggestionsInfo = $suggestionsInfoService;
		}
		$info = $suggestionsInfo->getInfo( [ 'resetCache' => true ] );

		$topicsInfo = $info['topics'] ?? [];
		$tasksInfo = $info['tasks'] ?? [];

		foreach ( $taskTypes as $taskType ) {
			foreach ( $topics as $topic ) {
				$taskInfoForTopic = $topicsInfo[ $topic ][ 'tasks' ];
				$taskCounts[ $taskType ][ $topic ] = $taskInfoForTopic[ $taskType ][ 'count' ];
			}
			$taskTypeCounts[ $taskType ] = $tasksInfo[ $taskType ][ 'totalCount' ];
		}
		foreach ( $topics as $topic ) {
			$topicCounts[ $topic ] = $topicsInfo[ $topic ][ 'totalCount' ];
		}
		return [ $taskCounts, $taskTypeCounts, $topicCounts ];
	}

	/**
	 * @param int[][] $taskCounts task type ID => topic ID => count
	 * @param int[] $taskTypeCounts task type ID => total count
	 */
	private function reportTaskCounts( array $taskCounts, array $taskTypeCounts ): void {
		$wiki = WikiMap::getCurrentWikiId();
		$counter = $this->getServiceContainer()->getStatsFactory()
			->withComponent( 'GrowthExperiments' )
			->getCounter( 'tasktype_count' );
		foreach ( $taskTypeCounts as $taskTypeId => $taskTypeCount ) {
			$counter
				->setLabel( 'wiki', $wiki )
				->setLabel( 'tasktype', $taskTypeId )
				->incrementBy( $taskTypeCount );
		}
	}

	/**
	 * @param string[] $taskTypes List of task type IDs to count for
	 * @param string[] $topics List of topic IDs to count for
	 * @param int[][] $taskCounts task type ID => topic ID => count
	 * @param int[] $taskTypeCounts task type ID => total count
	 * @param int[] $topicCounts topic ID => total count
	 */
	private function printResults( $taskTypes, $topics, array $taskCounts, array $taskTypeCounts, array $topicCounts ) {
		$output = $this->getOption( 'output', 'ascii-table' );
		if ( $output === 'none' ) {
			return;
		} elseif ( $output === 'json' ) {
			$this->output( FormatJson::encode( [
				'taskCounts' => $taskCounts,
				'taskTypeCounts' => $taskTypeCounts,
				'topicCounts' => $topicCounts,
			], false, FormatJson::UTF8_OK ) );
			return;
		}

		// Output header
		$this->output( str_pad( 'Topic', 25, ' ' ) . ' ' );
		foreach ( $taskTypes as $taskType ) {
			$this->output( str_pad( $taskType, 10, ' ' ) . ' ' );
		}
		$this->output( "\n" . str_repeat( '-', 80 ) . "\n" );

		foreach ( $topics as $topic ) {
			$this->output( str_pad( $topic, 25, ' ' ) . ' ' );
			foreach ( $taskTypes as $taskType ) {
				$this->output( str_pad( (string)$taskCounts[$taskType][$topic], 3, ' ', STR_PAD_RIGHT )
							   . str_repeat( ' ', 8 ) );
			}
			$this->output( "\n" );
		}
	}

}

// @codeCoverageIgnoreStart
$maintClass = ListTaskCounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
