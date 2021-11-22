<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;

interface TaskSetFilter {

	/**
	 * Filter out tasks from the TaskSet. Order is preserved.
	 * This is not particularly efficient; the taskset should not have more than a few tasks.
	 * @param TaskSet $taskSet
	 * @param int $maxLength Return at most this many tasks (used to avoid wasting time on
	 *   checking tasks we won't need).
	 * @return TaskSet
	 */
	public function filter( TaskSet $taskSet, int $maxLength = PHP_INT_MAX ): TaskSet;
}
