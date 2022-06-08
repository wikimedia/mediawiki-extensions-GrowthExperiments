<?php

namespace GrowthExperiments\NewcomerTasks;

interface NewcomerTasksInfo {
	/**
	 * Get information about available newcomer tasks segmented by task and topic.
	 *
	 * @param array $options Associative array of options:
	 *   - resetCache (bool, default false): ignore and replace the cached result.
	 * @return array An associative array with three elements:
	 *   - tasks (array): a list of task type ID => total count
	 *   - topics (array): a list of topic ID => total count => count per task type ID
	 *   - totalCount (int)
	 */
	public function getInfo( array $options = [] );
}
