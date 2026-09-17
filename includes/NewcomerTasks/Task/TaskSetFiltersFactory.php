<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;

/**
 * Builds the TaskSetFilters that limit the task suggestions for a user.
 *
 * Every caller that suggests tasks for a user must build the filters with this service.
 * CacheDecorator keeps one task set per user and regenerates it when the requested filters
 * differ, so two callers with different filters evict each other's task set.
 */
class TaskSetFiltersFactory {

	public const array CONSTRUCTOR_OPTIONS = [
		'GENewcomerTasksMaxInterestsForQueries',
	];

	public function __construct(
		private readonly NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		private readonly FeatureManager $featureManager,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Whether the suggestions for the given user are limited by their interests rather than by
	 * their topic preferences.
	 *
	 * This is the condition newFromUser() branches on. Callers that present the filters to the
	 * user must ask this rather than testing the experiment themselves, so that what they show
	 * cannot disagree with the filters the suggestions were built from.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function usesInterestFilters( UserIdentity $user ): bool {
		return $this->featureManager->isEarlyOnboardingExperimentTreatment( $user );
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
		if ( !$this->usesInterestFilters( $user ) ) {
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
		if ( $this->usesInterestFilters( $user ) ) {
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
			$this->options->get( 'GENewcomerTasksMaxInterestsForQueries' ),
		);
	}

}
