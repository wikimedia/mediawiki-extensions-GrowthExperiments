<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use MediaWiki\User\UserIdentity;

/**
 * Builds the TaskSetFilters that limit the task suggestions for a user.
 *
 * Every caller that suggests tasks for a user must build the filters with this service.
 * CacheDecorator keeps one task set per user and regenerates it when the requested filters
 * differ, so two callers with different filters evict each other's task set.
 */
class TaskSetFiltersFactory {

	public function __construct(
		private readonly NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
	) {
	}

	/**
	 * Build the task set filters for the given user.
	 *
	 * @param UserIdentity $user
	 * @param string[]|null $taskTypeFilters Task type IDs, or null to use the task type
	 *   preference of the user.
	 * @return TaskSetFilters
	 */
	public function newFromUser( UserIdentity $user, ?array $taskTypeFilters = null ): TaskSetFilters {
		return new TaskSetFilters(
			$taskTypeFilters ?? $this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
			$this->newcomerTasksUserOptionsLookup->getTopics( $user ),
			$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user )
		);
	}

}
