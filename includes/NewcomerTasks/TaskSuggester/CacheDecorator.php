<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use JobQueueGroup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StatusValue;
use WANObjectCache;

/**
 * A TaskSuggester decorator which uses WANObjectCache to get/set TaskSets.
 */
class CacheDecorator implements TaskSuggester, LoggerAwareInterface {

	use LoggerAwareTrait;

	private const CACHE_VERSION = 3;

	/** @var TaskSuggester */
	private $taskSuggester;

	/** @var WANObjectCache */
	private $cache;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/** @var TaskSetListener */
	private $taskSetListener;

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param JobQueueGroup $jobQueueGroup
	 * @param WANObjectCache $cache
	 * @param TaskSetListener $taskSetListener
	 */
	public function __construct(
		TaskSuggester $taskSuggester,
		JobQueueGroup $jobQueueGroup,
		WANObjectCache $cache,
		TaskSetListener $taskSetListener
	) {
		$this->taskSuggester = $taskSuggester;
		$this->cache = $cache;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->logger = new NullLogger();
		$this->taskSetListener = $taskSetListener;
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
		$resetCache = $options['resetCache'] ?? false;
		$revalidateCache = $options['revalidateCache'] ?? true;
		$excludePageIds = $options['excludePageIds'] ?? [];
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
				$user, $taskSetFilters, $limit, $useCache, $resetCache, $revalidateCache, $excludePageIds
			) {
				// This callback is always invoked each time getWithSetCallback is called,
				// because we need to examine the contents of the cache (if any) before
				// deciding whether to return those contents or if they need to be regenerated.

				if ( $useCache
					 && !$resetCache
					 && $oldValue instanceof TaskSet
					 && $oldValue->filtersEqual( $taskSetFilters )
					 && $oldValue->count()
				) {
					// There's a cached value we can use; we need to randomize and potentially
					// revalidate it.
					// &$ttl needs to be set to UNCACHEABLE so that WANObjectCache
					// doesn't attempt a set() after returning the existing value.
					$ttl = $this->cache::TTL_UNCACHEABLE;

					if ( $revalidateCache ) {
						// Filter out cached tasks which have already been done.
						// Filter before limiting, so they can be replaced by other tasks.
						$newValue = $this->taskSuggester->filter( $user, $oldValue );
					} else {
						$newValue = $oldValue;
					}
					$this->logger->debug( 'CacheDecorator hit', [
						'user' => $user->getName(),
						'taskTypes' => implode( '|', $taskSetFilters->getTaskTypeFilters() ),
						'topics' => implode( '|', $taskSetFilters->getTopicFilters() ) ?: null,
						'limit' => $limit,
						'revalidateCache' => $revalidateCache,
						'ttl' => $ttl,
						'cachedTaskCount' => $oldValue->count(),
						'validTaskCount' => ( $newValue instanceof TaskSet ) ? $newValue->count() : null,
					] );
					if ( $newValue instanceof TaskSet ) {
						// Shuffle the contents again (they were shuffled when first placed into the
						// cache) and return only the subset of tasks that the requester asked for.
						$newValue->randomSort();
						$newValue->truncate( $limit );
						$this->runTaskSetListener( $newValue );
					}
					return $newValue;
				}

				// We don't have a task set, or the taskset filters in the request don't match
				// what is stored in the cache, or using the cached value was explicitly diallowed
				// by the caller. Call the search backend and return the results.
				// N.B. we cache whatever the taskSuggester returns, which could be a StatusValue,
				// so when retrieving items from the cache we need to check the type before assuming
				// we are working with a TaskSet.
				$result = $this->taskSuggester->suggest(
					$user,
					$taskSetFilters->getTaskTypeFilters(),
					$taskSetFilters->getTopicFilters(),
					SearchTaskSuggester::DEFAULT_LIMIT,
					null,
					[ 'excludePageIds' => $excludePageIds ]
				);
				if ( $result instanceof TaskSet && $result->count() ) {
					$result->randomSort();
					if ( $useCache || $resetCache ) {
						// Schedule a job to refresh the taskset before the cache
						// expires.
						try {
							$this->jobQueueGroup->lazyPush(
								new NewcomerTasksCacheRefreshJob( [
									'userId' => $user->getId(),
									'jobReleaseTimestamp' => (int)wfTimestamp() +
										// Process the job the day before the cache expires.
										( $this->cache::TTL_WEEK - $this->cache::TTL_DAY ),
								] )
							);
						} catch ( \JobQueueError $jobQueueError ) {
							// Ignore jobqueue errors.
						}
					}
					$this->runTaskSetListener( $result );
				}
				if ( !$useCache && !$resetCache ) {
					$ttl = $this->cache::TTL_UNCACHEABLE;
				}
				$this->logger->debug( 'CacheDecorator miss', [
					'user' => $user->getName(),
					'taskTypes' => implode( '|', $taskSetFilters->getTaskTypeFilters() ),
					'topics' => implode( '|', $taskSetFilters->getTopicFilters() ) ?: null,
					'limit' => $limit,
					'useCache' => $useCache,
					'taskCount' => ( $result instanceof TaskSet ) ? $result->count() : null,
				] );
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

	/**
	 *
	 * @param TaskSet|StatusValue $taskSet
	 */
	private function runTaskSetListener( $taskSet ) {
		if ( $taskSet instanceof StatusValue ) {
			return;
		}
		$this->taskSetListener->run( $taskSet );
	}

}
