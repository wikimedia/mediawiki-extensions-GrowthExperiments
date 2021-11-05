<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use IContextSource;
use MediaWiki\User\UserIdentity;
use RequestContext;

/**
 * A TaskSuggester decorator that injects data for task type "quality gates" into a TaskSet.
 *
 */
class QualityGateDecorator implements TaskSuggester {

	/** @var TaskSuggester */
	private $taskSuggester;

	/** @var ImageRecommendationSubmissionLogFactory */
	private $imageRecommendationSubmissionLogFactory;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var int */
	private $imageRecommendationCountForUser;

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param ConfigurationLoader $configurationLoader
	 * @param ImageRecommendationSubmissionLogFactory $imageRecommendationSubmissionLogFactory
	 */
	public function __construct(
		TaskSuggester $taskSuggester,
		ConfigurationLoader $configurationLoader,
		ImageRecommendationSubmissionLogFactory $imageRecommendationSubmissionLogFactory
	) {
		$this->taskSuggester = $taskSuggester;
		$this->imageRecommendationSubmissionLogFactory = $imageRecommendationSubmissionLogFactory;
		$this->configurationLoader = $configurationLoader;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = [],
		array $topicFilter = [],
		?int $limit = null,
		?int $offset = null,
		array $options = []
	) {
		$tasks = $this->taskSuggester->suggest( $user, $taskTypeFilter, $topicFilter, $limit, $offset, $options );
		// TODO: Split out QualityGates methods into separate classes per task type.
		if ( $tasks instanceof TaskSet ) {
			$context = RequestContext::getMain();
			$tasks->setQualityGateConfigForTaskType( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
				[ 'dailyLimit' => $this->isImageRecommendationDailyTaskLimitExceeded(
					$user,
					$context
				), 'dailyCount' => $this->getImageRecommendationTasksDoneByUserForCurrentDay(
					$user,
					$context
				) ] );
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
	 * @param IContextSource $contextSource
	 * @return bool|null
	 */
	private function isImageRecommendationDailyTaskLimitExceeded(
		UserIdentity $user,
		IContextSource $contextSource
	): ?bool {
		/** @var ImageRecommendationTaskType $imageRecommendationTaskType */
		$imageRecommendationTaskType =
			$this->configurationLoader->getTaskTypes()[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
		if ( !$imageRecommendationTaskType ) {
			return null;
		}
		return $this->getImageRecommendationTasksDoneByUserForCurrentDay(
				$user,
				$contextSource
			) >=
			// @phan-suppress-next-line PhanUndeclaredMethod
			$imageRecommendationTaskType->getMaxTasksPerDay();
	}

	/**
	 * @param UserIdentity $user
	 * @param IContextSource $contextSource
	 * @return int
	 */
	private function getImageRecommendationTasksDoneByUserForCurrentDay(
		UserIdentity $user,
		IContextSource $contextSource
	): int {
		if ( $this->imageRecommendationCountForUser ) {
			return $this->imageRecommendationCountForUser;
		}
		$imageRecommendationSubmissionLog =
			$this->imageRecommendationSubmissionLogFactory->newImageRecommendationSubmissionLog(
				$user,
				$contextSource
			);
		$this->imageRecommendationCountForUser = $imageRecommendationSubmissionLog->count();
		return $this->imageRecommendationCountForUser;
	}
}
