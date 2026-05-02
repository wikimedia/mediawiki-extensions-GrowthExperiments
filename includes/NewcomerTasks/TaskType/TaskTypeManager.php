<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;

class TaskTypeManager {

	private ?array $taskTypes = null;
	private ?array $availableTaskTypes = null;
	private ?array $availableTaskTypesOnNextEdit = null;
	private ?array $userTaskTypeFilter = null;
	private ?array $unavailableTaskTypes = null;
	private ?string $suggestedNextTask = null;

	public function __construct(
		private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		private UserEditTracker $userEditTracker,
		private ConfigurationLoader $configurationLoader,
		private LevelingUpManager $levelingUpManager,
	) {
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @return string[]
	 */
	public function getTaskTypesForUser( UserIdentity $userIdentity ): array {
		if ( $this->userTaskTypeFilter === null ) {
			$this->loadTaskTypes( $userIdentity );
		}

		if ( !$this->userTaskTypeFilter && $this->suggestedNextTask ) {
			return array_merge( $this->userTaskTypeFilter, [ $this->suggestedNextTask ] );
		}
		return array_values( $this->userTaskTypeFilter );
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @return string[]
	 */
	public function getUnavailableTaskTypes( UserIdentity $userIdentity ): array {
		if ( $this->unavailableTaskTypes === null ) {
			$this->loadTaskTypes( $userIdentity );
		}
		return $this->unavailableTaskTypes;
	}

	public function getAvailableTaskTypes( UserIdentity $userIdentity ): array {
		if ( $this->availableTaskTypes === null ) {
			$this->loadTaskTypes( $userIdentity );
		}
		return array_values( $this->availableTaskTypes );
	}

	public function getAvailableTaskTypesOnNextEdit( UserIdentity $userIdentity ): array {
		if ( $this->availableTaskTypesOnNextEdit === null ) {
			$this->loadTaskTypes( $userIdentity );
		}
		return $this->availableTaskTypesOnNextEdit;
	}

	private function loadTaskTypes( UserIdentity $userIdentity ): void {
		if ( $this->taskTypes !== null ) {
			return;
		}
		// All enabled configured task types
		$this->taskTypes = $this->configurationLoader->getTaskTypes();
		[ $this->userTaskTypeFilter ] = $this->filterLimitReachedAddLink(
			$userIdentity,
			// User selected task types, excluding filtered out by ab-testing
			$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $userIdentity )
		);
		// compute un/available tasks, excluding filtered out by ab-testing and by add link limit
		[ $this->availableTaskTypes, $this->unavailableTaskTypes ] = $this->filterLimitReachedAddLink(
			$userIdentity,
			$this->newcomerTasksUserOptionsLookup->convertTaskTypes( array_keys( $this->taskTypes ), $userIdentity ),
		);
		// compute un/available tasks, excluding filtered out by ab-testing and by add link limit but this time
		// simulating editCount + 1 to predict available tasks on next edit
		[ $this->availableTaskTypesOnNextEdit ] = $this->filterLimitReachedAddLink(
			$userIdentity,
			$this->newcomerTasksUserOptionsLookup->convertTaskTypes( array_keys( $this->taskTypes ), $userIdentity ),
			1
		);
		// pre-compute the next task suggestion if the result of filtering is no available task types
		if ( !$this->userTaskTypeFilter && $this->unavailableTaskTypes ) {
			$this->suggestedNextTask = $this->getSuggestedTaskTypeForUser(
				$userIdentity, $this->unavailableTaskTypes[ array_key_last( $this->unavailableTaskTypes ) ]
			);
		}
	}

	/**
	 * Remove AddLink task if the user edit count exceeds the maximum set via community configuration.
	 * @param UserIdentity $user
	 * @param string[] $taskTypesToFilter Task types IDs.
	 * @param int $limitError
	 * @return string[][] first element are the filtered in task types IDs, second element.are the filtered out.
	 * @see GrowthExperiments\Config\Schemas\SuggestedEditsSchema::link_recommendation
	 */
	private function filterLimitReachedAddLink(
		UserIdentity $user,
		array $taskTypesToFilter,
		int $limitError = 0
	): array {
		$filtered = [];
		$editCount = $this->userEditTracker->getUserEditCount( $user );
		if ( !$this->taskTypes ) {
			return [];
		}
		if (
			!$editCount ||
			!in_array( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID, $taskTypesToFilter )
		) {
			return [ $taskTypesToFilter, $filtered ];
		}
		$addLinkTaskType = $this->taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID];
		if ( $addLinkTaskType instanceof LinkRecommendationTaskType ) {
			$max = $addLinkTaskType->getMaximumEditsTaskIsAvailable();
			$limitReached = $editCount >= ( $max - $limitError );
			if ( $max && $limitReached ) {
				$filtered[] = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;
				$taskTypesToFilter = array_diff(
					$taskTypesToFilter,
					$filtered
				);
			}
		}
		return [
			$taskTypesToFilter,
			$filtered,
		];
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @param string $lastUnavailableTask
	 * @return string|null
	 */
	private function getSuggestedTaskTypeForUser( UserIdentity $userIdentity, string $lastUnavailableTask ): ?string {
		return $this->levelingUpManager->suggestNewTaskTypeForUser(
			$userIdentity,
			$lastUnavailableTask,
			false,
			$this->getAvailableTaskTypes( $userIdentity ),
			true
		);
	}

}
