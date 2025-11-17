<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;

class ReviseToneTaskSetFilter extends AbstractTaskSetFilter {

	/**
	 * @inheritDoc
	 */
	public function filter( TaskSet $taskSet, int $maxLength = PHP_INT_MAX ): TaskSet {
		$validTasks = [];
		$firstReviseToneTask = null;
		foreach ( $taskSet as $task ) {
			if (
				$firstReviseToneTask === null &&
				$task->getTaskType()->getId() === ReviseToneTaskTypeHandler::TASK_TYPE_ID
			) {
				$firstReviseToneTask = $task;
				continue;
			}
			$validTasks[] = $task;
		}

		if ( $firstReviseToneTask ) {
			array_unshift( $validTasks, $firstReviseToneTask );
		}

		return $this->copyValidAndInvalidTasksToNewTaskSet( $taskSet, $validTasks, $taskSet->getInvalidTasks() );
	}
}
