<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\Util;
use IContextSource;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
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

	/** @var LinkRecommendationSubmissionLogFactory */
	private $linkRecommendationSubmissionLogFactory;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var int */
	private $imageRecommendationCountForUser;

	/** @var int */
	private $linkRecommendationCountForUser;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param ConfigurationLoader $configurationLoader
	 * @param ImageRecommendationSubmissionLogFactory $imageRecommendationSubmissionLogFactory
	 * @param LinkRecommendationSubmissionLogFactory $linkRecommendationSubmissionLogFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		TaskSuggester $taskSuggester,
		ConfigurationLoader $configurationLoader,
		ImageRecommendationSubmissionLogFactory $imageRecommendationSubmissionLogFactory,
		LinkRecommendationSubmissionLogFactory $linkRecommendationSubmissionLogFactory,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->taskSuggester = $taskSuggester;
		$this->imageRecommendationSubmissionLogFactory = $imageRecommendationSubmissionLogFactory;
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationSubmissionLogFactory = $linkRecommendationSubmissionLogFactory;
		$this->userOptionsLookup = $userOptionsLookup;
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
			$imageRecommendationTaskType =
				$this->configurationLoader->getTaskTypes()[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
			if ( $imageRecommendationTaskType instanceof ImageRecommendationTaskType ) {
				$desktopEnabled = $this->userOptionsLookup->getBoolOption(
					$user,
					SuggestedEdits::ADD_IMAGE_DESKTOP_PREF
				);
				$tasks->setQualityGateConfigForTaskType( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID, [
					'dailyLimit' => $this->isImageRecommendationDailyTaskLimitExceeded(
						$user,
						$context,
						$imageRecommendationTaskType
					),
					'dailyCount' => $this->getImageRecommendationTasksDoneByUserForCurrentDay(
						$user,
						$context
					),
					'mobileOnly' => $desktopEnabled || Util::isMobile( $context->getSkin() )
				] );
			}
			$linkRecommendationTaskType =
				$this->configurationLoader->getTaskTypes()[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
			if ( $linkRecommendationTaskType instanceof LinkRecommendationTaskType ) {
				$tasks->setQualityGateConfigForTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
					[
						'dailyLimit' => $this->isLinkRecommendationDailyTaskLimitExceeded(
							$user,
							$context,
							$linkRecommendationTaskType
						),
						'dailyCount' => $this->getLinkRecommendationTasksDoneByUserForCurrentDay( $user,
							$context )
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
	 * @param IContextSource $contextSource
	 * @param ImageRecommendationTaskType $imageRecommendationTaskType
	 * @return bool|null
	 */
	private function isImageRecommendationDailyTaskLimitExceeded(
		UserIdentity $user,
		IContextSource $contextSource,
		ImageRecommendationTaskType $imageRecommendationTaskType
	): ?bool {
		return $this->getImageRecommendationTasksDoneByUserForCurrentDay(
				$user,
				$contextSource
			) >=
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

	/**
	 * Check if daily limit of link recommendation is exceeded for a user.
	 *
	 * TODO: Move this into a link-recommendation specific class.
	 *
	 * @param UserIdentity $user
	 * @param IContextSource $contextSource
	 * @param LinkRecommendationTaskType $linkRecommendationTaskType
	 * @return bool|null
	 */
	private function isLinkRecommendationDailyTaskLimitExceeded(
		UserIdentity $user,
		IContextSource $contextSource,
		LinkRecommendationTaskType $linkRecommendationTaskType
	): ?bool {
		return $this->getLinkRecommendationTasksDoneByUserForCurrentDay(
				$user,
				$contextSource
			) >=
			$linkRecommendationTaskType->getMaxTasksPerDay();
	}

	/**
	 * @param UserIdentity $user
	 * @param IContextSource $contextSource
	 * @return int
	 */
	private function getLinkRecommendationTasksDoneByUserForCurrentDay(
		UserIdentity $user,
		IContextSource $contextSource
	): int {
		if ( $this->linkRecommendationCountForUser ) {
			return $this->linkRecommendationCountForUser;
		}
		$linkRecommendationSubmissionLog =
			$this->linkRecommendationSubmissionLogFactory->newLinkRecommendationSubmissionLog(
				$user,
				$contextSource
			);
		$this->linkRecommendationCountForUser = $linkRecommendationSubmissionLog->count();
		return $this->linkRecommendationCountForUser;
	}
}
