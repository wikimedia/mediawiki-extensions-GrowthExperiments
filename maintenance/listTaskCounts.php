<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\OresTopicTrait;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\NullTaskTypeHandler;
use Maintenance;
use MediaWiki\MediaWikiServices;
use Status;
use StatusValue;
use User;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';
require_once dirname( __DIR__ ) . '/includes/NewcomerTasks/OresTopicTrait.php';

/**
 * List the number of tasks available for each topic
 */
class ListTaskCounts extends Maintenance {

	use OresTopicTrait;

	/** @var string 'growth' or 'ores' */
	private $topicType;

	/** @var TaskSuggester */
	private $taskSuggester;

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

		[ $taskTypes, $topics ] = $this->getTaskTypesAndTopics();
		[ $taskCounts, $taskTypeCounts, $topicCounts ] = $this->getStats( $taskTypes, $topics );
		if ( $this->hasOption( 'statsd' ) ) {
			$this->reportTaskCounts( $taskCounts, $taskTypeCounts );
		}
		$this->printResults( $taskTypes, $topics, $taskCounts, $taskTypeCounts, $topicCounts );
	}

	/**
	 * This method replaces the normal configuration loader and as such must be called first.
	 * @return array{0:string[],1:string[]} [ task type ID list, topic ID list ]
	 */
	private function getTaskTypesAndTopics(): array {
		$nullTaskType = NullTaskTypeHandler::getNullTaskType( '_null' );
		$this->replaceConfigurationLoader( $this->topicType === 'ores', [ $nullTaskType ] );

		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );

		$allTaskTypes = array_keys( $growthServices->getNewcomerTasksConfigurationLoader()->getTaskTypes() );
		$taskTypes = $this->getOption( 'tasktype', $allTaskTypes );
		if ( array_diff( $taskTypes, $allTaskTypes ) ) {
			$this->fatalError( 'Invalid task types: ' . implode( ', ', array_diff( $taskTypes, $allTaskTypes ) ) );
		}
		$taskTypes = array_diff( $taskTypes, [ '_null' ] );

		$allTopics = array_keys( $growthServices->getNewcomerTasksConfigurationLoader()->getTopics() );
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
		// FIXME: Integrate with GrowthExperimentsSuggestionsInfo service, need a more robust
		//   implementation of OresTopicTrait functionality first.
		$taskCounts = $taskTypeCounts = $topicCounts = [];
		foreach ( $taskTypes as $taskType ) {
			if ( $taskType === '_null' ) {
				continue;
			}
			foreach ( $topics as $topic ) {
				$taskCounts[$taskType][$topic] = $this->getTaskCount( [ $taskType ], [ $topic ] );
			}
			$taskTypeCounts[$taskType] = $this->getTaskCount( [ $taskType ], [] );
		}
		foreach ( $topics as $topic ) {
			$topicCounts[$topic] = $this->getTaskCount( [ '_null' ], [ $topic ] );
		}

		return [ $taskCounts, $taskTypeCounts, $topicCounts ];
	}

	/**
	 * @param string[] $taskTypes
	 * @param string[] $topics
	 * @return int
	 */
	private function getTaskCount( $taskTypes, $topics ): int {
		if ( !$this->taskSuggester ) {
			$services = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
			$this->taskSuggester = $services->getTaskSuggesterFactory()->create();
		}
		$taskSetFilters = new TaskSetFilters( $taskTypes, $topics );
		$tasks = $this->taskSuggester->suggest( new User, $taskSetFilters, 0, null, [ 'useCache' => false ] );
		if ( $tasks instanceof StatusValue ) {
			$this->fatalError( Status::wrap( $tasks )->getWikiText( false, false, 'en' ) );
		}
		return $tasks->getTotalCount();
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
