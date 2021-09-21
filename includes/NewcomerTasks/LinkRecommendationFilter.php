<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;

class LinkRecommendationFilter {

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/**
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct( LinkRecommendationStore $linkRecommendationStore ) {
		$this->linkRecommendationStore = $linkRecommendationStore;
	}

	/**
	 * Filter out link recommendation tasks that don't have a DB entry for recommendation data.
	 *
	 * @param TaskSet $taskSet
	 * @return TaskSet A new TaskSet with tasks not backed by DB entries removed from the tasks field and placed into
	 * the invalidTasks field.
	 */
	public function filter( TaskSet $taskSet ): TaskSet {
		$invalidTasks = [];
		$validTasks = [];
		foreach ( $taskSet as $task ) {
			if ( $task->getTaskType() instanceof LinkRecommendationTaskType &&
				!$this->linkRecommendationStore->getByLinkTarget( $task->getTitle() ) ) {
				$invalidTasks[] = $task;
			} else {
				$validTasks[] = $task;
			}
		}
		return new TaskSet(
			$validTasks, $taskSet->getTotalCount(), $taskSet->getOffset(), $taskSet->getFilters(), $invalidTasks
		);
	}
}
