<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use JobQueueGroup;
use MediaWiki\User\UserIdentity;
use WANObjectCache;

/**
 * A TaskSuggester decorator which uses WANObjectCache to get/set TaskSets.
 */
class CacheDecorator implements TaskSuggester {

	private const CACHE_VERSION = 1;

	/** @var TaskSuggester */
	private $taskSuggester;

	/** @var WANObjectCache */
	private $cache;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param JobQueueGroup $jobQueueGroup
	 * @param WANObjectCache $cache
	 */
	public function __construct(
		TaskSuggester $taskSuggester,
		JobQueueGroup $jobQueueGroup,
		WANObjectCache $cache
	) {
		$this->taskSuggester = $taskSuggester;
		$this->cache = $cache;
		$this->jobQueueGroup = $jobQueueGroup;
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
		$useCache = $options['useCache'] ?? true;
		$revalidateCache = $options['revalidateCache'] ?? true;
		$debug = $options['debug'] ?? false;
		$taskSetFilters = new TaskSetFilters( $taskTypeFilter, $topicFilter );
		$limit = $limit ?? SearchTaskSuggester::DEFAULT_LIMIT;

		if ( $debug || $limit > SearchTaskSuggester::DEFAULT_LIMIT ) {
			return $this->taskSuggester->suggest( $user, $taskTypeFilter, $topicFilter, $limit, $offset, $options );
		}

		return $this->cache->getWithSetCallback(
			$this->cache->makeKey(
				'GrowthExperiments-NewcomerTasks-TaskSet',
				self::CACHE_VERSION,
				$user->getId()
			),
			$this->cache::TTL_WEEK,
			function ( $oldValue, &$ttl ) use (
				$user, $taskSetFilters, $limit, $useCache, $revalidateCache
			) {
				// This callback is always invoked each time getWithSetCallback is called,
				// because we need to examine the contents of the cache (if any) before
				// deciding whether to return those contents or if they need to be regenerated.
				if ( $useCache && $oldValue instanceof TaskSet && $oldValue->filtersEqual( $taskSetFilters ) ) {
					// &$ttl needs to be set to UNCACHEABLE so that WANObjectCache
					// doesn't attempt a set() after returning the existing value.
					$ttl = $this->cache::TTL_UNCACHEABLE;
					if ( $revalidateCache ) {
						// Filter out cached tasks which have already been done.
						// Filter before limiting, so they can be replace by other tasks.
						$newValue = $this->taskSuggester->filter( $user, $oldValue );
					} else {
						$newValue = $oldValue;
					}
					if ( $newValue instanceof TaskSet ) {
						// Shuffle the contents again (they were shuffled when first placed into the
						// cache) and return only the subset of tasks that the requester asked for.
						$newValue->randomSort();
						$newValue->truncate( $limit );
					}
					return $newValue;
				}
				// We don't have a task set, or the taskset filters in the request don't match
				// what is stored in the cache. Call the search backend and return the results.
				// N.B. we cache whatever the taskSuggester returns, which could be a StatusValue,
				// so when retrieving items from the cache we need to check the type before assuming
				// we are working with a TaskSet.
				$result = $this->taskSuggester->suggest(
					$user,
					$taskSetFilters->getTaskTypeFilters(),
					$taskSetFilters->getTopicFilters(),
					SearchTaskSuggester::DEFAULT_LIMIT
				);
				if ( $result instanceof TaskSet && $result->count() ) {
					$result->randomSort();
					// Schedule a job to refresh the taskset before the cache
					// expires.
					$this->jobQueueGroup->lazyPush(
						new NewcomerTasksCacheRefreshJob( [
							'userId' => $user->getId(),
							'jobReleaseTimestamp' => (int)wfTimestamp() +
								// Process the job the day before the cache expires.
								( $this->cache::TTL_WEEK - $this->cache::TTL_DAY ),
						] )
					);
				}
				return $result;
			},
			// minAsOf is used to reject values below the defined timestamp. By
			// settings minAsOf = INF (PHP's constant for the infinite), we are
			// telling WANObjectCache to always invoke the callback. See
			// callback comment for more on why.
			[ 'minAsOf' => INF ]
		);
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ) {
		return $this->taskSuggester->filter( $user, $taskSet );
	}

}
