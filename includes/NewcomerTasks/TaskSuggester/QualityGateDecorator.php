<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddSectionImage\SectionImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use MediaWiki\User\UserIdentity;

/**
 * A TaskSuggester decorator that injects data for task type "quality gates" into a TaskSet.
 *
 */
class QualityGateDecorator implements TaskSuggester {

	/** @var TaskSuggester */
	private $taskSuggester;

	/** @var ImageRecommendationSubmissionLogFactory */
	private $imageRecommendationSubmissionLogFactory;

	/** @var LinkRecommendationSubmissionLogFactory */
	private $linkRecommendationSubmissionLogFactory;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var int */
	private $imageRecommendationCountForUser;

	/** @var int */
	private $linkRecommendationCountForUser;

	private SectionImageRecommendationSubmissionLogFactory $sectionImageRecommendationSubmissionLogFactory;
	private ?int $sectionImageRecommendationCountForUser = null;

	/** @var CampaignConfig */
	private $campaignConfig;

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param ConfigurationLoader $configurationLoader
	 * @param ImageRecommendationSubmissionLogFactory $imageRecommendationSubmissionLogFactory
	 * @param SectionImageRecommendationSubmissionLogFactory $sectionImageRecommendationSubmissionLogFactory
	 * @param LinkRecommendationSubmissionLogFactory $linkRecommendationSubmissionLogFactory
	 * @param CampaignConfig $campaignConfig
	 */
	public function __construct(
		TaskSuggester $taskSuggester,
		ConfigurationLoader $configurationLoader,
		ImageRecommendationSubmissionLogFactory $imageRecommendationSubmissionLogFactory,
		SectionImageRecommendationSubmissionLogFactory $sectionImageRecommendationSubmissionLogFactory,
		LinkRecommendationSubmissionLogFactory $linkRecommendationSubmissionLogFactory,
		CampaignConfig $campaignConfig
	) {
		$this->taskSuggester = $taskSuggester;
		$this->imageRecommendationSubmissionLogFactory = $imageRecommendationSubmissionLogFactory;
		$this->sectionImageRecommendationSubmissionLogFactory = $sectionImageRecommendationSubmissionLogFactory;
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationSubmissionLogFactory = $linkRecommendationSubmissionLogFactory;
		$this->campaignConfig = $campaignConfig;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	) {
		$tasks = $this->taskSuggester->suggest( $user, $taskSetFilters, $limit, $offset, $options );
		// TODO: Split out QualityGates methods into separate classes per task type.
		if ( $tasks instanceof TaskSet ) {
			$imageRecommendationTaskType =
				$this->configurationLoader->getTaskTypes()[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
			if ( $imageRecommendationTaskType instanceof ImageRecommendationTaskType ) {
				$tasks->setQualityGateConfigForTaskType( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID, [
					'dailyLimit' => $this->isImageRecommendationDailyTaskLimitExceeded(
						$user,
						$imageRecommendationTaskType
					),
					'dailyCount' => $this->getImageRecommendationTasksDoneByUserForCurrentDay( $user ),
				] );
			}
			$sectionImageRecommendationTaskType =
				$this->configurationLoader->getTaskTypes()[SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID]
				?? null;
			if ( $sectionImageRecommendationTaskType instanceof SectionImageRecommendationTaskType ) {
				$tasks->setQualityGateConfigForTaskType( SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID, [
					'dailyLimit' => $this->isSectionImageRecommendationDailyTaskLimitExceeded(
						$user,
						$sectionImageRecommendationTaskType
					),
					'dailyCount' => $this->getSectionImageRecommendationTasksDoneByUserForCurrentDay( $user ),
				] );
			}

			$linkRecommendationTaskType =
				$this->configurationLoader->getTaskTypes()[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
			if ( $linkRecommendationTaskType instanceof LinkRecommendationTaskType ) {
				$tasks->setQualityGateConfigForTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
					[
						'dailyLimit' => $this->isLinkRecommendationDailyTaskLimitExceeded(
							$user,
							$linkRecommendationTaskType
						),
						'dailyCount' => $this->getLinkRecommendationTasksDoneByUserForCurrentDay( $user )
					] );
			}
		}
		return $tasks;
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ) {
		return $this->taskSuggester->filter( $user, $taskSet );
	}

	/**
	 * Check if daily limit of image recommendation is exceeded for a user.
	 *
	 * TODO: Move this into a image-recommendation specific class.
	 *
	 * @param UserIdentity $user
	 * @param ImageRecommendationTaskType $imageRecommendationTaskType
	 * @return bool|null
	 */
	private function isImageRecommendationDailyTaskLimitExceeded(
		UserIdentity $user,
		ImageRecommendationTaskType $imageRecommendationTaskType
	): ?bool {
		if ( $this->campaignConfig->shouldSkipImageRecommendationDailyTaskLimitForUser( $user ) ) {
			return false;
		}
		return $this->getImageRecommendationTasksDoneByUserForCurrentDay( $user )
			>= $imageRecommendationTaskType->getMaxTasksPerDay();
	}

	private function getImageRecommendationTasksDoneByUserForCurrentDay(
		UserIdentity $user
	): int {
		if ( $this->imageRecommendationCountForUser ) {
			return $this->imageRecommendationCountForUser;
		}
		$imageRecommendationSubmissionLog =
			$this->imageRecommendationSubmissionLogFactory
				->newImageRecommendationSubmissionLog( $user );
		$this->imageRecommendationCountForUser = $imageRecommendationSubmissionLog->count();
		return $this->imageRecommendationCountForUser;
	}

	/**
	 * Check if daily limit of link recommendation is exceeded for a user.
	 *
	 * TODO: Move this into a link-recommendation specific class.
	 *
	 * @param UserIdentity $user
	 * @param LinkRecommendationTaskType $linkRecommendationTaskType
	 * @return bool|null
	 */
	private function isLinkRecommendationDailyTaskLimitExceeded(
		UserIdentity $user,
		LinkRecommendationTaskType $linkRecommendationTaskType
	): ?bool {
		return $this->getLinkRecommendationTasksDoneByUserForCurrentDay( $user )
			>= $linkRecommendationTaskType->getMaxTasksPerDay();
	}

	private function getLinkRecommendationTasksDoneByUserForCurrentDay(
		UserIdentity $user
	): int {
		if ( $this->linkRecommendationCountForUser ) {
			return $this->linkRecommendationCountForUser;
		}
		$linkRecommendationSubmissionLog =
			$this->linkRecommendationSubmissionLogFactory
				->newLinkRecommendationSubmissionLog( $user );
		$this->linkRecommendationCountForUser = $linkRecommendationSubmissionLog->count();
		return $this->linkRecommendationCountForUser;
	}

	/**
	 * Check if daily limit of section image recommendation is exceeded for a user.
	 *
	 * TODO: Move this into a section-image-recommendation specific class.
	 *
	 * @param UserIdentity $user
	 * @param SectionImageRecommendationTaskType $sectionImageRecommendationTaskType
	 * @return bool|null
	 */
	private function isSectionImageRecommendationDailyTaskLimitExceeded(
		UserIdentity $user,
		SectionImageRecommendationTaskType $sectionImageRecommendationTaskType
	): ?bool {
		return $this->getSectionImageRecommendationTasksDoneByUserForCurrentDay( $user )
			>= $sectionImageRecommendationTaskType->getMaxTasksPerDay();
	}

	private function getSectionImageRecommendationTasksDoneByUserForCurrentDay(
		UserIdentity $user
	): int {
		if ( $this->sectionImageRecommendationCountForUser ) {
			return $this->sectionImageRecommendationCountForUser;
		}
		$sectionImageRecommendationSubmissionLog =
			$this->sectionImageRecommendationSubmissionLogFactory
				->newSectionImageRecommendationSubmissionLog( $user );
		$this->sectionImageRecommendationCountForUser = $sectionImageRecommendationSubmissionLog->count();
		return $this->sectionImageRecommendationCountForUser;
	}

}
