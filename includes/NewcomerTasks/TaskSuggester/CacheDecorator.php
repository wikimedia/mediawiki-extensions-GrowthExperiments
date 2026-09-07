<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use MediaWiki\JobQueue\Exceptions\JobQueueError;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * A TaskSuggester decorator which uses WANObjectCache to get/set TaskSets.
 *
 * The cache holds a pool of tasks which is deeper than what one request serves. For
 * interest-based task sets the pool is served as a fresh random slice on every read; for
 * topic-based and unfiltered task sets the pool is the size of one request.
 */
class CacheDecorator implements TaskSuggester, LoggerAwareInterface {

	use LoggerAwareTrait;

	private const CACHE_VERSION = 6;

	/**
	 * Target size of the cached pool of interest-based tasks. Telemetry from the
	 * early-onboarding experiment should drive any change to it (T435365).
	 */
	public const INTEREST_POOL_SIZE = 50;

	public function __construct(
		private readonly TaskSuggester $taskSuggester,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly WANObjectCache $cache,
		private readonly TaskSetListener $taskSetListener,
		private readonly JsonCodec $jsonCodec,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly TitleFactory $titleFactory
	) {
		$this->logger = new NullLogger();
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	): TaskSet|StatusValue {
		$useCache = $options['useCache'] ?? true;
		$resetCache = $options['resetCache'] ?? false;
		$revalidateCache = $options['revalidateCache'] ?? true;
		$excludePageIds = $options['excludePageIds'] ?? [];
		$debug = $options['debug'] ?? false;
		$limit ??= SearchTaskSuggester::DEFAULT_LIMIT;
		$isInterestTaskSet = $taskSetFilters->isInterestBased();

		if ( $debug || $limit > $this->getPoolSize( $taskSetFilters ) ) {
			return $this->taskSuggester->suggest( $user, $taskSetFilters, $limit, $offset, $options );
		}

		$isHit = true;
		$json = $this->cache->getWithSetCallback(
			$this->cache->makeKey(
				'GrowthExperiments-NewcomerTasks-TaskSet',
				$user->getId()
			),
			( $useCache || $resetCache ) ? $this->cache::TTL_WEEK : $this->cache::TTL_UNCACHEABLE,
			function () use (
				$user, $taskSetFilters, $useCache, $resetCache, $excludePageIds, &$isHit
			) {
				$isHit = false;

				// We don't have a task set, or the taskset filters in the request don't match
				// what is stored in the cache, or using the cached value was explicitly diallowed
				// by the caller. Call the search backend and return the results.
				// N.B. we cache whatever the taskSuggester returns, which could be a StatusValue,
				// so when retrieving items from the cache we need to check the type before assuming
				// we are working with a TaskSet.
				$result = $this->taskSuggester->suggest(
					$user,
					$taskSetFilters,
					$this->getPoolSize( $taskSetFilters ),
					null,
					[ 'excludePageIds' => $excludePageIds ]
				);
				if ( $result instanceof TaskSet && $result->count() ) {
					if ( !$taskSetFilters->isInterestBased() ) {
						// An interest pool gets a fresh random slice on every read, so the
						// stored order does not matter.
						$result->randomSort();
					}
					if ( ( $useCache || $resetCache ) && !$taskSetFilters->isInterestBased() ) {
						// Schedule a job to refresh the taskset before the cache
						// expires. An interest pool gets no refresh job.
						try {
							if ( !$user->isRegistered() ) {
								$this->logger->error(
									'Scheduling NewcomerTasksCacheRefreshJob for non-registered user',
									[
										'userId' => $user->getId(),
										'exception' => new \RuntimeException( 'T419172' ),
									]
								);
							}
							$this->jobQueueGroup->lazyPush(
								new JobSpecification( NewcomerTasksCacheRefreshJob::JOB_NAME, [
									'userId' => $user->getId(),
									'jobReleaseTimestamp' => (int)wfTimestamp() +
										// Process the job the day before the cache expires.
										( $this->cache::TTL_WEEK - $this->cache::TTL_DAY ),
								] )
							);
						} catch ( JobQueueError ) {
							// Ignore jobqueue errors.
						}
					}
				}
				return $this->serialize( $result );
			},
			[
				'version' => self::CACHE_VERSION,
				'touchedCallback' => function ( $oldValue ) use (
					$useCache, $resetCache, $taskSetFilters
				) {
					// Examine the contents of the cache (if any) before
					// deciding whether to return those contents or if they need to be regenerated.
					$oldValue = $this->deserialize( $oldValue );
					if ( $useCache
						 && !$resetCache
						 && $oldValue instanceof TaskSet
						 && $oldValue->filtersEqual( $taskSetFilters )
						 && $oldValue->count()
					) {
						return null;
					} else {
						// Force regeneration
						return INF;
					}
				},
			]
		);

		$result = $this->deserialize( $json );

		// Read the count before the steps below change the task set, which they do in place.
		$cachedTaskCount = null;
		if ( $result instanceof TaskSet ) {
			$cachedTaskCount = $result->count();
			if ( $revalidateCache && $isHit ) {
				// Filter out cached tasks which have already been done.
				// Filter before limiting, so they can be replaced by other tasks.
				$result = $this->taskSuggester->filter( $user, $result );
			}
		}
		if ( $result instanceof TaskSet ) {
			if ( $isInterestTaskSet ) {
				// Interest queries are sorted by relevance and give the same pool every
				// time, so all of the variety comes from the order drawn here. Articles the
				// requester already has are dropped, to show each article at most once in
				// a browsing session. A regenerated pool never holds them, because the
				// search which built it excluded them.
				$this->serveInterestSlice( $result, $isHit ? $excludePageIds : [] );
			} else {
				// Shuffle the contents again (they were shuffled when first placed into the
				// cache) and return only the subset of tasks that the requester asked for.
				$result->randomSort();
			}
		}

		if ( $isHit ) {
			$this->logger->debug( 'CacheDecorator hit', [
				'user' => $user->getName(),
				'taskTypes' => implode( '|', $taskSetFilters->getTaskTypeFilters() ),
				'topics' => implode( '|', $taskSetFilters->getTopicFilters() ) ?: null,
				'interests' => implode( '|', $taskSetFilters->getInterestFilters() ) ?: null,
				'limit' => $limit,
				'revalidateCache' => $revalidateCache,
				'cachedTaskCount' => $cachedTaskCount,
				'validTaskCount' => ( $result instanceof TaskSet ) ? $result->count() : null,
			] );
		} else {
			$this->logger->debug( 'CacheDecorator miss', [
				'user' => $user->getName(),
				'taskTypes' => implode( '|', $taskSetFilters->getTaskTypeFilters() ),
				'topics' => implode( '|', $taskSetFilters->getTopicFilters() ) ?: null,
				'interests' => implode( '|', $taskSetFilters->getInterestFilters() ) ?: null,
				'limit' => $limit,
				'useCache' => $useCache,
				'taskCount' => ( $result instanceof TaskSet ) ? $result->count() : null,
			] );
		}

		// Discard extra items when the method was called with $limit < DEFAULT_LIMIT,
		// and run listeners.
		if ( $result instanceof TaskSet && $result->count() ) {
			$result->truncate( $limit );
			$this->runTaskSetListener( $result );
		}
		return $result;
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ): TaskSet|StatusValue {
		return $this->taskSuggester->filter( $user, $taskSet );
	}

	/**
	 * The number of tasks the decorator asks the inner suggester for. An interest pool must
	 * hold more tasks than one request serves, because interest queries are sorted by
	 * relevance and give the same results every time.
	 */
	private function getPoolSize( TaskSetFilters $taskSetFilters ): int {
		return $taskSetFilters->isInterestBased()
			? self::INTEREST_POOL_SIZE
			: SearchTaskSuggester::DEFAULT_LIMIT;
	}

	/**
	 * Order an interest pool for the request which serves it, and drop the articles the
	 * requester already has. suggest() truncates the result to the requested limit.
	 * @param TaskSet $pool Modified in place.
	 * @param int[] $excludePageIds Page IDs the requester already has.
	 */
	private function serveInterestSlice( TaskSet $pool, array $excludePageIds ): void {
		$tasks = iterator_to_array( $pool );
		if ( $tasks && $excludePageIds ) {
			$tasks = $this->rejectPages( $tasks, $excludePageIds );
		}
		$pool->retainTasks( $this->interleaveByInterest( $tasks ) );
	}

	/**
	 * Drop the tasks whose article is one of the given page IDs.
	 * @param Task[] $tasks
	 * @param int[] $excludePageIds
	 * @return Task[]
	 */
	private function rejectPages( array $tasks, array $excludePageIds ): array {
		// Warm the title cache for reading the IDs, like ProtectionFilter does.
		$linkBatch = $this->linkBatchFactory->newLinkBatch(
			array_map( static fn ( Task $task ) => $task->getTitle(), $tasks )
		);
		$linkBatch->setCaller( __METHOD__ );
		$linkBatch->execute();

		$excluded = array_fill_keys( $excludePageIds, true );
		return array_filter( $tasks, function ( Task $task ) use ( $excluded ) {
			// A page which does not exist any more has the ID 0, which a caller can also
			// ask to exclude. Such a task is not one the requester already has, so keep it.
			$articleId = $this->titleFactory->newFromLinkTarget( $task->getTitle() )->getArticleID();
			return !$articleId || !isset( $excluded[$articleId] );
		} );
	}

	/**
	 * Return the items of a list in a random order. Protected so that tests can replace it
	 * with a fixed order, like SearchStrategy::shuffleQueryOrder() does.
	 */
	protected function shuffleList( array $list ): array {
		shuffle( $list );
		return $list;
	}

	/**
	 * Order the pool round-robin across the interests, with a uniform random order inside
	 * each interest. Relevance decides which articles reach the pool, not which of them a
	 * request serves.
	 * @param Task[] $tasks
	 * @return Task[]
	 */
	private function interleaveByInterest( array $tasks ): array {
		$buckets = [];
		foreach ( $tasks as $task ) {
			$topics = $task->getTopics();
			$buckets[$topics ? $topics[0]->getId() : ''][] = $task;
		}

		// Shuffle the interests too, so that no interest always comes first. Grouping by
		// position and then flattening takes one task from each interest in turn.
		$byPosition = [];
		foreach ( $this->shuffleList( array_keys( $buckets ) ) as $interest ) {
			foreach ( $this->shuffleList( $buckets[$interest] ) as $position => $task ) {
				$byPosition[$position][] = $task;
			}
		}
		return array_merge( ...$byPosition );
	}

	private function runTaskSetListener( TaskSet|StatusValue $taskSet ): void {
		if ( $taskSet instanceof StatusValue ) {
			return;
		}
		$this->taskSetListener->run( $taskSet );
	}

	/**
	 * Serialize a value for caching. Serializing StatusValue is left to the default caching logic.
	 */
	private function serialize( TaskSet|StatusValue $value ): string|StatusValue {
		if ( $value instanceof TaskSet ) {
			return $this->jsonCodec->serialize( $value );
		}
		return $value;
	}

	/**
	 * Deserialize a cached value. StatusValue is handled by PHP serialization so we just pass
	 * it through here.
	 */
	private function deserialize( string|StatusValue $value ): TaskSet|StatusValue {
		if ( $value instanceof StatusValue ) {
			return $value;
		} else {
			return $this->jsonCodec->deserialize( $value, TaskSet::class );
		}
	}

}
