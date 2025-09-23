<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\MediaWikiEventIngress;

use ExtensionRegistry;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\Event\PageRevisionUpdatedListener;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;

/**
 * Handle newcomer milestone achievements
 *
 * Triggers notifications when newcomers reach editing thresholds for add-a-link tasks.
 * Criteria for notifications:
 * - User has exactly X edits.
 * - User completed at least 1 add-a-link task.
 * - User still has add-a-link task type selected.
 */
class NewcomerMilestoneIngress extends DomainEventIngress implements PageRevisionUpdatedListener {
	private UserEditTracker $userEditTracker;
	private NewcomerTasksUserOptionsLookup $userOptionsLookup;
	private UserImpactLookup $userImpactLookup;
	private Config $config;
	private ConfigurationLoader $configurationLoader;

	/**
	 * @param UserEditTracker $userEditTracker For edit count retrieval
	 * @param ConfigurationLoader $configurationLoader
	 * @param NewcomerTasksUserOptionsLookup $userOptionsLookup For task selection checking
	 * @param UserImpactLookup $userImpactLookup For computed impact data
	 * @param Config $config MediaWiki configuration
	 */
	public function __construct(
		UserEditTracker $userEditTracker,
		ConfigurationLoader $configurationLoader,
		NewcomerTasksUserOptionsLookup $userOptionsLookup,
		UserImpactLookup $userImpactLookup,
		Config $config
	) {
		$this->userEditTracker = $userEditTracker;
		$this->configurationLoader = $configurationLoader;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userImpactLookup = $userImpactLookup;
		$this->config = $config;
	}

	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
		$extensionRegistry = ExtensionRegistry::getInstance();
		if ( !$extensionRegistry->isLoaded( 'Echo' ) ) {
			return;
		}
		$user = $event->getPerformer();

		if (
			!$user->isRegistered() ||
			!$this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ||
			!$this->config->get( 'GENewcomerTasksStarterDifficultyEnabled' ) ||
			$event->isRevert()
		) {
			return;
		}
		$editCount = $this->userEditTracker->getUserEditCount( $user );
		$milestoneThreshold = $this->getMilestoneThreshold();
		if ( $this->userMeetsCriteriaForNotification( $user, $editCount, $milestoneThreshold ) ) {
			$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $user );
			if ( $userImpact === null ) {
				return;
			}
			$editCountByTaskType = $userImpact->getEditCountByTaskType();
			$addLinkEditCount = $editCountByTaskType[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? 0;
			if ( $addLinkEditCount > 0 ) {
				if ( $milestoneThreshold !== null ) {
					$this->createMilestoneNotification( $user, $milestoneThreshold, $userImpact );
				}
			}
		}
	}

	/**
	 * Check milestone requirements.
	 */
	private function userMeetsCriteriaForNotification(
		UserIdentity $user,
		?int $editCount,
		?int $milestoneThreshold
	): bool {
		if ( $editCount === null ) {
			return false;
		}
		// REVIEW ensure how user_editcount is updated, $editCount + 1 only will work if
		// this event is handled AFTER the UserEditCountUpdate is processes
		if ( $milestoneThreshold === null || $editCount + 1 !== $milestoneThreshold ) {
			return false;
		}
		$hasAddLinkSelected = $this->hasAddLinkTaskSelected( $user );
		if ( !$hasAddLinkSelected ) {
			return false;
		}
		return true;
	}

	/**
	 * Get milestone threshold.
	 *
	 * @return int|null The milestone threshold or null if not configured
	 */
	private function getMilestoneThreshold(): ?int {
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$taskTypeId = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;

		if ( !isset( $taskTypes[$taskTypeId] ) ) {
			return null;
		}

		$taskType = $taskTypes[$taskTypeId];
		if ( !( $taskType instanceof LinkRecommendationTaskType ) ) {
			return null;
		}
		return $taskType->getMaximumEditsTaskIsAvailable();
	}

	/**
	 * Check if user still has add-a-link selected.
	 */
	private function hasAddLinkTaskSelected( UserIdentity $user ): bool {
		$selectedTaskTypes = $this->userOptionsLookup->getTaskTypeFilter( $user );
		return in_array( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID, $selectedTaskTypes, true );
	}

	/**
	 * Create milestone notification using Echo extension.
	 *
	 * @param UserIdentity $user The user to notify
	 * @param int $threshold The configured threshold to show as part of the message
	 * @param ExpensiveUserImpact $userImpact User impact data for stats
	 * @return void
	 */
	private function createMilestoneNotification(
		UserIdentity $user, int $threshold, ExpensiveUserImpact $userImpact
	): void {
		Event::create( [
			'type' => 'newcomer-milestone-reached',
			'agent' => $user,
			'extra' => [
				'threshold' => $threshold,
				'views-count' => array_sum( $userImpact->getDailyTotalViews() ),
			],
		] );
	}
}
