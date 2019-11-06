<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\Assert;

/**
 * A TaskSuggester which always starts with the same preconfigured set of tasks, and applies
 * filter/limit/offset to them.
 * Intended for testing and local frontend development. To use it, register a MediaWikiServices
 * hook along the lines of
 *
 *     $wgHooks['MediaWikiServices'][] = function ( MediaWikiServices $services ) {
 *         $services->redefineService( 'GrowthExperimentsTaskSuggester', function () {
 *             $taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
 *             return new StaticTaskSuggester( [
 *                 new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
 *                 new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
 *             ] );
 *         } );
 *     };
 */
class StaticTaskSuggester implements TaskSuggester {

	/** @var Task[] */
	private $tasks;

	/**
	 * @param Task[] $tasks
	 */
	public function __construct( array $tasks ) {
		Assert::parameterElementType( Task::class, $tasks, '$suggestions' );
		$this->tasks = $tasks;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = null,
		array $topicFilter = null,
		$limit = null,
		$offset = null
	) {
		$filteredTasks = array_filter( $this->tasks,
			function ( Task $task ) use ( $taskTypeFilter, $topicFilter ) {
				return $taskTypeFilter === null
					|| in_array( $task->getTaskType()->getId(), $taskTypeFilter, true );
			}
		);
		return new TaskSet( array_slice( $filteredTasks, $offset, $limit ),
			count( $filteredTasks ), $offset ?: 0 );
	}
}
