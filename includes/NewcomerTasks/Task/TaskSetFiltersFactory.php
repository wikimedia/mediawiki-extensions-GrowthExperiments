<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\FeatureManager;
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

	/** The maximum number of interests used for suggestions. */
	public const int MAX_INTERESTS = 10;

	public function __construct(
		private readonly NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		private readonly FeatureManager $featureManager,
	) {
	}

	/**
	 * Get the interests that limit the suggestions for the given user.
	 * @param UserIdentity $user
	 * @return string[] A list of at most MAX_INTERESTS prefixed article titles. Empty when
	 *   the user is not in the treatment group of the early-onboarding experiment, or has no
	 *   interests stored.
	 * @see \GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic
	 */
	public function getInterestFilters( UserIdentity $user ): array {
		if ( !$this->featureManager->isEarlyOnboardingExperimentTreatment( $user ) ) {
			return [];
		}
		return $this->readInterests( $user );
	}

	/**
	 * Build the task set filters for the given user.
	 *
	 * Users in the treatment group of the early-onboarding experiment get interest filters.
	 * The topic preferences and the topic match mode are not read for them. All other users
	 * get topic filters.
	 *
	 * @param UserIdentity $user
	 * @param string[]|null $taskTypeFilters Task type IDs, or null to use the task type
	 *   preference of the user.
	 * @return TaskSetFilters
	 */
	public function newFromUser( UserIdentity $user, ?array $taskTypeFilters = null ): TaskSetFilters {
		$taskTypeFilters ??= $this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user );
		$topicsMatchMode = $this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user );
		if ( $this->featureManager->isEarlyOnboardingExperimentTreatment( $user ) ) {
			return new TaskSetFilters( $taskTypeFilters, [], $topicsMatchMode, $this->readInterests( $user ) );
		}
		return new TaskSetFilters(
			$taskTypeFilters,
			$this->newcomerTasksUserOptionsLookup->getTopics( $user ),
			$topicsMatchMode
		);
	}

	/**
	 * @param UserIdentity $user
	 * @return string[] The first MAX_INTERESTS stored interests, or an empty list.
	 */
	private function readInterests( UserIdentity $user ): array {
		return array_slice(
			$this->newcomerTasksUserOptionsLookup->getInterests( $user ),
			0,
			self::MAX_INTERESTS
		);
	}

}
