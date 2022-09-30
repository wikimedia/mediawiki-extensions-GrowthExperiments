<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\CachedSuggestionsInfo;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use Maintenance;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * List the number of tasks available for each topic
 */
class ListTaskCounts extends Maintenance {

	/** @var string 'growth' or 'ores' */
	private $topicType;

	/** @var ConfigurationLoader */
	private $configurationLoader;

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

	/** @inheritDoc */
	public function execute() {
		if ( $this->getConfig()->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$this->output( "Local tasks disabled\n" );
			return;
		}

		$this->topicType = $this->getOption( 'topictype', 'growth' );
		if ( !in_array( $this->topicType, [ 'ores', 'growth' ], true ) ) {
			$this->fatalError( 'topictype must be one of: growth, ores' );
		}

		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$newcomerTaskConfigurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$this->configurationLoader = new TopicDecorator(
			$newcomerTaskConfigurationLoader,
			$this->topicType == 'ores'
		);

		[ $taskTypes, $topics ] = $this->getTaskTypesAndTopics();
		[ $taskCounts, $taskTypeCounts, $topicCounts ] = $this->getStats( $taskTypes, $topics );
		if ( $this->hasOption( 'statsd' ) ) {
			$this->reportTaskCounts( $taskCounts, $taskTypeCounts );
		}
		$this->printResults( $taskTypes, $topics, $taskCounts, $taskTypeCounts, $topicCounts );
	}

	/**
	 * Get task types and topics to list task counts for
	 *
	 * @return array{0:string[],1:string[]} [ task type ID list, topic ID list ]
	 */
	private function getTaskTypesAndTopics(): array {
		$allTaskTypes = array_keys( $this->configurationLoader->getTaskTypes() );
		$taskTypes = $this->getOption( 'tasktype', $allTaskTypes );
		if ( array_diff( $taskTypes, $allTaskTypes ) ) {
			$this->fatalError( 'Invalid task types: ' . implode( ', ', array_diff( $taskTypes, $allTaskTypes ) ) );
		}

		$allTopics = array_keys( $this->configurationLoader->getTopics() );
		$topics = $this->getOption( 'topic', $allTopics );
		if ( array_diff( $topics, $allTopics ) ) {
			$this->fatalError( 'Invalid topics: ' . implode( ', ', array_diff( $topics, $allTopics ) ) );
		}

		return [ $taskTypes, $topics ];
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
		$mwServices = MediaWikiServices::getInstance();
		$services = GrowthExperimentsServices::wrap( $mwServices );
		// Cache stats for Growth topics since they're also used in SpecialNewcomerTasksInfo
		$shouldCacheStats = $this->topicType === 'growth';
		$suggestionsInfoService = new SuggestionsInfo(
			$services->getTaskSuggesterFactory(),
			$services->getTaskTypeHandlerRegistry(),
			$this->configurationLoader
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
		[ 'topics' => $topicsInfo, 'tasks' => $tasksInfo ] = $info;

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
		// Limit to link recommendations to avoid excessive use of statsd metrics as we don't
		// care too much about the others. Maybe there will be a nicer way to handle this in
		// the future with Prometheus.
		$taskTypeId = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;
		$taskData = $taskCounts[$taskTypeId] ?? null;
		if ( $taskData === null ) {
			$this->output( "No link recommendation task type, skipping statsd\n" );
			return;
		}

		$taskTypeCount = $taskTypeCounts[$taskTypeId];
		$dataFactory = MediaWikiServices::getInstance()->getPerDbNameStatsdDataFactory();
		foreach ( $taskData as $topic => $count ) {
			$dataFactory->updateCount( "growthexperiments.taskcount.$taskTypeId.$topic", $count );
		}
		$dataFactory->updateCount( "growthexperiments.tasktypecount.$taskTypeId", $taskTypeCount );
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

$maintClass = ListTaskCounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
