<?php

namespace GrowthExperiments\NewcomerTasks;

use DeferredUpdates;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddImage\CacheBackedImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use IBufferingStatsdDataFactory;
use MediaWiki\MediaWikiServices;
use WANObjectCache;

/**
 * Called from CacheDecorator after a TaskSet is fetched.
 *
 * Used currently for fetching and caching recommendation data for image recommendation tasks.
 */
class TaskSetListener {

	/** @var WANObjectCache */
	private $cache;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/**
	 * @param WANObjectCache $cache
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct( WANObjectCache $cache, IBufferingStatsdDataFactory $statsdDataFactory ) {
		$this->cache = $cache;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/**
	 * Execute code after task set is fetched in CacheDecorator.
	 *
	 * FIXME Find a better way to structure the actions that different task types can require when a TaskSet is
	 * constructed.
	 *
	 * @param TaskSet $taskSet
	 */
	public function run( TaskSet $taskSet ): void {
		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate( function () use ( $taskSet, $fname ) {
			foreach ( $taskSet as $task ) {
				if ( $task->getTaskType() instanceof ImageRecommendationTaskType ) {
					$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
					CacheBackedImageRecommendationProvider::getWithSetCallback(
						$this->cache,
						$growthServices->getImageRecommendationProviderUncached(),
						$task->getTaskType(),
						$task->getTitle(),
						$fname,
						$this->statsdDataFactory
					);
				}
			}
		} );
	}
}
