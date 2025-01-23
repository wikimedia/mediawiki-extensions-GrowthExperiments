<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;

/**
 * Filter out link recommendation tasks that don't have a DB entry for recommendation data.
 */
class LinkRecommendationFilter extends AbstractTaskSetFilter implements TaskSetFilter {

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	public function __construct( LinkRecommendationStore $linkRecommendationStore ) {
		$this->linkRecommendationStore = $linkRecommendationStore;
	}

	/** @inheritDoc */
	public function filter( TaskSet $taskSet, int $maxLength = PHP_INT_MAX ): TaskSet {
		$invalidTasks = [];
		$validTasks = [];
		foreach ( $taskSet as $task ) {
			if ( count( $validTasks ) >= $maxLength ) {
				break;
			}
			if ( $task->getTaskType() instanceof LinkRecommendationTaskType &&
				!$this->linkRecommendationStore->getByLinkTarget( $task->getTitle() ) ) {
				$invalidTasks[] = $task;
			} else {
				$validTasks[] = $task;
			}
		}
		return $this->copyValidAndInvalidTasksToNewTaskSet( $taskSet, $validTasks, $invalidTasks );
	}
}
