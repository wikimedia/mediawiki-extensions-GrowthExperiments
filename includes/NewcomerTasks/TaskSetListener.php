<?php

namespace GrowthExperiments\NewcomerTasks;

use DeferredUpdates;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddImage\CacheBackedImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use MediaWiki\MediaWikiServices;
use WANObjectCache;

/**
 * Called from CacheDecorator after a TaskSet is returned (either from cache or by calling the
 * decorated suggester).
 *
 * Used currently for fetching and caching recommendation data for image recommendation tasks.
 */
class TaskSetListener {

	/** @var WANObjectCache */
	private $cache;

	/**
	 * @param WANObjectCache $cache
	 */
	public function __construct( WANObjectCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Execute code after task set is returned from CacheDecorator (either from cache or by
	 * calling the decorated suggester).
	 *
	 * FIXME Find a better way to structure the actions that different task types can require when a TaskSet is
	 * constructed.
	 *
	 * @param TaskSet $taskSet
	 */
	public function run( TaskSet $taskSet ): void {
		DeferredUpdates::addCallableUpdate( function () use ( $taskSet ) {
			foreach ( $taskSet as $task ) {
				if ( $task->getTaskType() instanceof ImageRecommendationTaskType ) {
					$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
					CacheBackedImageRecommendationProvider::getWithSetCallback(
						$this->cache,
						$growthServices->getImageRecommendationProviderUncached(),
						$task->getTaskType(),
						$task->getTitle()
					);
				}
			}
		} );
	}
}
