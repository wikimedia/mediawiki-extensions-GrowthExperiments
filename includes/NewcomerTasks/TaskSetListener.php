<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddImage\CacheBackedImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * Called from CacheDecorator after a TaskSet is returned (either from cache or by calling the
 * decorated suggester).
 *
 * Used currently for fetching and caching recommendation data for image recommendation tasks.
 */
class TaskSetListener {

	/** @var WANObjectCache */
	private $cache;

	private StatsFactory $statsFactory;

	public function __construct( WANObjectCache $cache, StatsFactory $statsFactory ) {
		$this->cache = $cache;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * Execute code after task set is returned from CacheDecorator (either from cache or by
	 * calling the decorated suggester).
	 *
	 * FIXME Find a better way to structure the actions that different task types can require when a TaskSet is
	 * constructed.
	 */
	public function run( TaskSet $taskSet ): void {
		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate( function () use ( $taskSet, $fname ) {
			foreach ( $taskSet as $task ) {
				if ( $task->getTaskType() instanceof ImageRecommendationBaseTaskType ) {
					$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
					CacheBackedImageRecommendationProvider::getWithSetCallback(
						$this->cache,
						$growthServices->getImageRecommendationProviderUncached(),
						$task->getTaskType(),
						$task->getTitle(),
						$fname,
						$this->statsFactory
					);
				}
			}
		} );
	}
}
