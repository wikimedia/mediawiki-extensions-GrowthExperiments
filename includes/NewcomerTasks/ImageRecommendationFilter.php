<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Filter out image recommendation tasks that have been marked as invalid in a temporary cache
 * in AddImageSubmissionHandler. This is needed because search index updates don't happen in real time.
 */
class ImageRecommendationFilter extends AbstractTaskSetFilter implements TaskSetFilter {

	/** @var WANObjectCache */
	private $cache;

	public function __construct( WANObjectCache $cache ) {
		$this->cache = $cache;
	}

	/** @inheritDoc */
	public function filter( TaskSet $taskSet, int $maxLength = PHP_INT_MAX ): TaskSet {
		$invalidTasks = [];
		$validTasks = [];
		foreach ( $taskSet as $task ) {
			if ( count( $validTasks ) >= $maxLength ) {
				break;
			}
			if ( !$task->getTaskType() instanceof ImageRecommendationBaseTaskType ) {
				$validTasks[] = $task;
				continue;
			}
			$result = $this->cache->get( self::makeKey(
				$this->cache,
				$task->getTaskType()->getId(),
				$task->getTitle()->getDBkey()
			) );
			if ( $result ) {
				$invalidTasks[] = $task;
			} else {
				$validTasks[] = $task;
			}
		}
		return $this->copyValidAndInvalidTasksToNewTaskSet( $taskSet, $validTasks, $invalidTasks );
	}

	/**
	 * Use a dedicated cache key for keeping track of image recommendations that have been invalidated.
	 *
	 * @param WANObjectCache $cache
	 * @param string $taskTypeId
	 * @param string $dbKey
	 * @return string
	 *
	 * @see AddImageSubmissionHandler::handle()
	 */
	public static function makeKey( WANObjectCache $cache, string $taskTypeId, string $dbKey ): string {
		return $cache->makeKey(
			'growthexperiments-invalidated-image-recommendations',
			$taskTypeId,
			$dbKey
		);
	}
}
