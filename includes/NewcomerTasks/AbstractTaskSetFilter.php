<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;

/**
 * Base class with a helper method to copy valid/invalid TaskSet data into a new TaskSet.
 */
abstract class AbstractTaskSetFilter implements TaskSetFilter {

	/**
	 * @param TaskSet $taskSet
	 * @param array $validTasks
	 * @param array $invalidTasks
	 * @return TaskSet
	 */
	public function copyValidAndInvalidTasksToNewTaskSet(
		TaskSet $taskSet, array $validTasks, array $invalidTasks
	): TaskSet {
		$filteredTaskSet = new TaskSet(
			$validTasks,
			$taskSet->getTotalCount(),
			$taskSet->getOffset(),
			$taskSet->getFilters(),
			$invalidTasks
		);
		$filteredTaskSet->setDebugData( $taskSet->getDebugData() );
		$filteredTaskSet->setQualityGateConfig( $taskSet->getQualityGateConfig() );
		return $filteredTaskSet;
	}

}
