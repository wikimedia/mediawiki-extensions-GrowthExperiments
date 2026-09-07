<?php

namespace GrowthExperiments\Tests\Unit;

use Exception;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Page\LinkBatch;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use TestLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator
 */
class CacheDecoratorTest extends MediaWikiUnitTestCase {

	private WANObjectCache $cache;
	private UserIdentityValue $user;
	/** @var JobSpecification[] Jobs the decorator pushed. */
	private array $pushedJobs;

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->user = new UserIdentityValue( 1000, 'Test' );
		$this->pushedJobs = [];
	}

	/**
	 * @dataProvider provideSuggest
	 * @param array $calls List of arrays with:
	 * - suggester TaskSuggester
	 * - args array: Arguments to TaskSuggester::suggest()
	 * @param TaskSet|StatusValue|Exception $expectedResult
	 */
	public function testSuggest(
		array $calls,
		$expectedResult
	) {
		foreach ( $calls as $i => $call ) {
			if ( $expectedResult instanceof Exception && $i === count( $calls ) - 1 ) {
				$this->expectException( get_class( $expectedResult ) );
			}
			$cacheDecorator = $this->newCacheDecorator( $call['suggester'] );
			$result = $cacheDecorator->suggest( ...$call['args'] );
		}
		if ( !( $expectedResult instanceof Exception ) ) {
			$this->assertEquals( $expectedResult, $result );
		}
	}

	public static function provideSuggest() {
		$user = new UserIdentityValue( 1000, 'Test' );
		$copyeditType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$linksType = new TaskType( 'links', TaskType::DIFFICULTY_EASY );
		// Use tasksets consisting of one task only, so we don't have to deal with randomization
		// of the task order messing up assertions.
		$taskSetFilters = new TaskSetFilters( [ 'copyedit' ], [] );
		$taskSetFilterLinks = new TaskSetFilters( [ 'links' ], [] );
		$taskSetFilterArt = new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] );
		$taskSetFilterCoffeeTopic = new TaskSetFilters( [ 'copyedit' ], [ 'Coffee' ] );
		$taskSetFilterCoffeeInterest = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] );
		$taskSetFilterTeaInterest = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Tea' ] );

		$taskA = new Task( $copyeditType, new TitleValue( NS_MAIN, 'Foo' ) );
		$taskB = new Task( $linksType, new TitleValue( NS_MAIN, 'Bar' ) );
		$taskC = new Task( $copyeditType, new TitleValue( NS_MAIN, 'Foo' ) );
		$taskC->setTopics( [ new Topic( 'arts' ) ] );
		$taskD = new Task( $copyeditType, new TitleValue( NS_MAIN, 'Espresso' ) );
		$taskD->setTopics( [ new InterestBasedTopic( 'Coffee', new TitleValue( NS_MAIN, 'Coffee' ) ) ] );

		$suggesterA = new StaticTaskSuggester( [ $taskA ] );
		$suggesterB = new StaticTaskSuggester( [ $taskB ] );
		$suggesterC = new StaticTaskSuggester( [ $taskC ] );
		$suggesterD = new StaticTaskSuggester( [ $taskD ] );

		$suggesterFailA = new class( [ $taskA ] )  extends StaticTaskSuggester {
			public function suggest(
				$user,
				$taskSetFilters,
				$limit = null,
				$offset = null,
				$options = []
			) {
				return StatusValue::newFatal( 'error' );
			}
		};
		$suggesterFailD = new class( [ $taskD ] ) extends StaticTaskSuggester {
			public function suggest(
				$user,
				$taskSetFilters,
				$limit = null,
				$offset = null,
				$options = []
			) {
				return StatusValue::newFatal( 'error' );
			}
		};

		return [
			'taskset on cache miss' => [
				'calls' => [
					[
						'suggester' => $suggesterA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskA ], 1, 0, $taskSetFilters ),
			],
			'error on cache miss' => [
				'calls' => [
					[
						'suggester' => $suggesterFailA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => StatusValue::newFatal( 'error' ),
			],
			'cache hit with cached taskset' => [
				'calls' => [
					[
						'suggester' => $suggesterA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterFailA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskA ], 1, 0, $taskSetFilters ),
			],
			'cache hit with cached error' => [
				'calls' => [
					[
						'suggester' => $suggesterFailA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterB,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterLinks,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskB ], 1, 0, $taskSetFilterLinks ),
			],
			'cache miss due to task filter' => [
				'calls' => [
					[
						'suggester' => $suggesterA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterB,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterLinks,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskB ], 1, 0, $taskSetFilterLinks ),
			],
			'cache miss due to topic filter' => [
				'calls' => [
					[
						'suggester' => $suggesterA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilters,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterC,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterArt,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskC ], 1, 0, $taskSetFilterArt ),
			],
			'cache miss due to interest filter change' => [
				'calls' => [
					[
						'suggester' => $suggesterD,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterCoffeeInterest,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterTeaInterest,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskA ], 1, 0, $taskSetFilterTeaInterest ),
			],
			'cache miss when interests replace topics with the same value' => [
				'calls' => [
					[
						'suggester' => $suggesterD,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterCoffeeTopic,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterA,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterCoffeeInterest,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskA ], 1, 0, $taskSetFilterCoffeeInterest ),
			],
			'cache hit with same interests' => [
				'calls' => [
					[
						'suggester' => $suggesterD,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterCoffeeInterest,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
					[
						'suggester' => $suggesterFailD,
						'args' => [
							'user' => $user,
							'taskSetFilters' => $taskSetFilterCoffeeInterest,
							'limit' => 15,
							'offset' => 0,
							'options' => [],
						],
					],
				],
				'expectedResult' => new TaskSet( [ $taskD ], 1, 0, $taskSetFilterCoffeeInterest ),
			],
		];
	}

	/**
	 * The pool the decorator asks the inner suggester for, and the limit a request serves,
	 * are two separate numbers. Only an interest task set gets a deeper pool.
	 * @dataProvider providePoolSize
	 */
	public function testPoolSize( TaskSetFilters $filters, int $expectedPoolSize ) {
		$recorder = $this->newRecordingTaskSuggester();

		$this->newCacheDecorator( $recorder )->suggest( $this->user, $filters, 5 );

		$this->assertSame( $expectedPoolSize, $recorder->calls[0]['limit'] );
	}

	public static function providePoolSize(): array {
		return [
			'unfiltered' => [
				new TaskSetFilters( [ 'copyedit' ] ), SearchTaskSuggester::DEFAULT_LIMIT,
			],
			'topics' => [
				new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] ), SearchTaskSuggester::DEFAULT_LIMIT,
			],
			'interests' => [
				new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] ),
				CacheDecorator::INTEREST_POOL_SIZE,
			],
		];
	}

	/**
	 * A request for more tasks than the pool holds cannot be served from the cache. The
	 * suggested edits module asks for DEFAULT_LIMIT plus a lookahead, which is above the pool
	 * of a topic-based task set but inside the pool of an interest task set.
	 * @dataProvider provideLimitsAroundThePool
	 * @param TaskSetFilters $filters
	 * @param int $limit
	 * @param int $expectedCalls How often the decorator asked the inner suggester.
	 */
	public function testLimitAroundThePool( TaskSetFilters $filters, int $limit, int $expectedCalls ) {
		$recorder = $this->newRecordingTaskSuggester();
		$decorator = $this->newCacheDecorator( $recorder );

		$decorator->suggest( $this->user, $filters, $limit );
		$decorator->suggest( $this->user, $filters, $limit );

		$this->assertCount( $expectedCalls, $recorder->calls );
	}

	public static function provideLimitsAroundThePool(): array {
		$topics = new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] );
		$interests = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] );
		return [
			'topics, inside the pool' => [ $topics, SearchTaskSuggester::DEFAULT_LIMIT, 1 ],
			'topics, above the pool' => [ $topics, SearchTaskSuggester::DEFAULT_LIMIT + 1, 2 ],
			'interests, module fetch with lookahead' => [ $interests, 20, 1 ],
			'interests, above the pool' => [
				$interests, CacheDecorator::INTEREST_POOL_SIZE + 1, 2,
			],
		];
	}

	/**
	 * @dataProvider provideOptionSemantics
	 * @param array[] $optionsPerCall Options for each suggest() call, in order.
	 * @param int $expectedCalls How often the decorator asked the inner suggester.
	 */
	public function testOptionSemantics( array $optionsPerCall, int $expectedCalls ) {
		$recorder = $this->newRecordingTaskSuggester();
		$decorator = $this->newCacheDecorator( $recorder );

		foreach ( $optionsPerCall as $options ) {
			$decorator->suggest( $this->user, new TaskSetFilters( [ 'copyedit' ] ), null, null, $options );
		}

		$this->assertCount( $expectedCalls, $recorder->calls );
	}

	public static function provideOptionSemantics(): array {
		return [
			'a stored task set is served from the cache' => [ [ [], [] ], 1 ],
			'useCache=false does not store the task set' => [ [ [ 'useCache' => false ], [] ], 2 ],
			'resetCache=true regenerates and stores the task set' => [
				[ [], [ 'resetCache' => true ], [] ], 2,
			],
			'debug=true ignores a stored task set' => [ [ [], [ 'debug' => true ] ], 2 ],
			'debug=true does not store the task set' => [ [ [ 'debug' => true ], [] ], 2 ],
		];
	}

	/**
	 * The excluded page IDs are the only option the decorator passes on when it regenerates
	 * the task set.
	 */
	public function testExcludePageIdsReachTheSuggesterOnACacheMiss() {
		$recorder = $this->newRecordingTaskSuggester();

		$this->newCacheDecorator( $recorder )->suggest(
			$this->user,
			new TaskSetFilters( [ 'copyedit' ] ),
			null,
			null,
			[ 'excludePageIds' => [ 11, 12 ], 'revalidateCache' => false ]
		);

		$this->assertSame( [ 'excludePageIds' => [ 11, 12 ] ], $recorder->calls[0]['options'] );
	}

	/**
	 * Every interest is represented in the served slice, because the slice takes tasks from
	 * the interests in turn.
	 */
	public function testInterestSliceIsRoundRobinAcrossInterests() {
		$filters = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee', 'Tea', 'Cocoa' ] );
		$suggester = new StaticTaskSuggester( $this->newInterestPool(
			[ 'Coffee' => 4, 'Tea' => 4, 'Cocoa' => 4 ]
		) );

		$taskSet = $this->newCacheDecorator( $suggester )->suggest( $this->user, $filters, 6 );

		$this->assertCount( 6, $taskSet );
		$interests = $this->interestsOf( $taskSet );
		$this->assertCount( 3, array_unique( array_slice( $interests, 0, 3 ) ) );
		$this->assertCount( 3, array_unique( array_slice( $interests, 3, 3 ) ) );
		$this->assertSameSize( $taskSet, array_unique( $this->titlesOf( $taskSet ) ) );
	}

	/**
	 * An interest pool holds the same articles in the same relevance order on every fetch, so
	 * the slice drawn on read is the only thing that can make two fetches differ.
	 */
	public function testInterestSliceIsDrawnFreshlyPerFetch() {
		$filters = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee', 'Tea' ] );
		$suggester = new StaticTaskSuggester( $this->newInterestPool( [ 'Coffee' => 10, 'Tea' => 10 ] ) );

		$first = $this->newCacheDecorator( $suggester, [], static fn ( array $list ) => $list )
			->suggest( $this->user, $filters, 8 );
		$second = $this->newCacheDecorator( $suggester, [], 'array_reverse' )
			->suggest( $this->user, $filters, 8 );

		$this->assertNotSame( $this->titlesOf( $first ), $this->titlesOf( $second ) );
		$this->assertCount( 8, $first );
		$this->assertCount( 8, $second );
		// The pool is bigger than the slice, but the count promises the whole pool.
		$this->assertSame( 20, $first->getTotalCount() );
	}

	/**
	 * Loading more tasks reads the cached pool and never returns an article the front end
	 * already has.
	 */
	public function testLoadMoreIsServedFromThePool() {
		$filters = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee', 'Tea' ] );
		$pool = $this->newInterestPool( [ 'Coffee' => 4, 'Tea' => 4 ] );
		$pageIds = array_combine( $this->titlesOf( $pool ), range( 101, 108 ) );

		$first = $this->newCacheDecorator( new StaticTaskSuggester( $pool ), $pageIds )
			->suggest( $this->user, $filters, 5 );
		$seen = $this->titlesOf( $first );
		$excludePageIds = array_map( static fn ( string $title ) => $pageIds[$title], $seen );

		// A suggester which cannot search proves the second request is a cache read.
		$second = $this->newCacheDecorator(
			new ErrorForwardingTaskSuggester( StatusValue::newFatal( 'error' ) ), $pageIds
		)->suggest( $this->user, $filters, 5, null, [ 'excludePageIds' => $excludePageIds ] );

		$this->assertCount( 3, $second );
		$this->assertSame( [], array_intersect( $seen, $this->titlesOf( $second ) ) );
	}

	/**
	 * When the pool holds nothing the requester has not seen, the response reports no tasks
	 * instead of searching again.
	 */

	/**
	 * A rebuild of an interest pool costs one search per interest per task type, so the
	 * decorator schedules no refresh job for it. Other task sets keep the job.
	 * @dataProvider provideRefreshJob
	 * @param TaskSetFilters $filters
	 * @param Task[] $pool
	 * @param int $expectedJobs
	 */
	public function testRefreshJobOnACacheMiss( TaskSetFilters $filters, array $pool, int $expectedJobs ) {
		$this->newCacheDecorator( new StaticTaskSuggester( $pool ) )
			->suggest( $this->user, $filters, 5 );

		$this->assertCount( $expectedJobs, $this->pushedJobs );
	}

	public static function provideRefreshJob(): array {
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$topicTask = new Task( $taskType, new TitleValue( NS_MAIN, 'Arts-1' ) );
		$topicTask->setTopics( [ new Topic( 'arts' ) ] );
		$interestTask = new Task( $taskType, new TitleValue( NS_MAIN, 'Coffee-1' ) );
		$interestTask->setTopics( [
			new InterestBasedTopic( 'Coffee', new TitleValue( NS_MAIN, 'Coffee' ) ),
		] );
		return [
			'topics' => [
				new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] ), [ $topicTask ], 1,
			],
			'interests' => [
				new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] ), [ $interestTask ], 0,
			],
		];
	}

	/**
	 * The hit log reports how deep the cached pool is, which the order and the excluded
	 * pages must not change.
	 */
	public function testHitLogReportsThePoolDepthWithoutRevalidation() {
		$filters = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] );
		$pool = $this->newInterestPool( [ 'Coffee' => 8 ] );
		$pageIds = array_combine( $this->titlesOf( $pool ), range( 101, 108 ) );

		$this->newCacheDecorator( new StaticTaskSuggester( $pool ), $pageIds )
			->suggest( $this->user, $filters, 5 );

		$cacheDecorator = $this->newCacheDecorator( new StaticTaskSuggester( $pool ), $pageIds );
		$logger = new TestLogger( true, null, true );
		$cacheDecorator->setLogger( $logger );

		$taskSet = $cacheDecorator->suggest( $this->user, $filters, 5, null, [
			'revalidateCache' => false,
			'excludePageIds' => [ 101, 102, 103, 104, 105 ],
		] );

		$this->assertCount( 3, $taskSet );
		$this->assertSame( 8, $this->hitContext( $logger )['cachedTaskCount'] );
	}

	/**
	 * @return array The context of the one cache hit the decorator logged.
	 */
	private function hitContext( TestLogger $logger ): array {
		$contexts = [];
		foreach ( $logger->getBuffer() as [ , $message, $context ] ) {
			if ( $message === 'CacheDecorator hit' ) {
				$contexts[] = $context;
			}
		}
		$this->assertCount( 1, $contexts, 'The decorator must log one cache hit.' );
		return $contexts[0];
	}

	/**
	 * A page which no longer exists has the ID 0. A caller may ask to exclude 0, which must
	 * not drop every such task.
	 */
	public function testExcludingPageIdZeroKeepsTasksWithoutAPage() {
		$filters = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] );
		$pool = $this->newInterestPool( [ 'Coffee' => 3 ] );
		// Only the first task has a page; the other two resolve to 0.
		$pageIds = [ $this->titlesOf( $pool )[0] => 101 ];

		$this->newCacheDecorator( new StaticTaskSuggester( $pool ), $pageIds )
			->suggest( $this->user, $filters, 5 );
		$taskSet = $this->newCacheDecorator(
			new ErrorForwardingTaskSuggester( StatusValue::newFatal( 'error' ) ), $pageIds
		)->suggest( $this->user, $filters, 5, null, [ 'excludePageIds' => [ 0, 101 ] ] );

		$this->assertCount( 2, $taskSet );
	}

	public function testExhaustedInterestPoolServesNoTasks() {
		$filters = new TaskSetFilters( [ 'copyedit' ], [], null, [ 'Coffee' ] );
		$pool = $this->newInterestPool( [ 'Coffee' => 3 ] );
		$pageIds = array_combine( $this->titlesOf( $pool ), range( 101, 103 ) );

		$this->newCacheDecorator( new StaticTaskSuggester( $pool ), $pageIds )
			->suggest( $this->user, $filters, 5 );
		$taskSet = $this->newCacheDecorator(
			new ErrorForwardingTaskSuggester( StatusValue::newFatal( 'error' ) ), $pageIds
		)->suggest( $this->user, $filters, 5, null, [ 'excludePageIds' => [ 101, 102, 103 ] ] );

		$this->assertInstanceOf( TaskSet::class, $taskSet );
		$this->assertCount( 0, $taskSet );
	}

	/**
	 * A topic-based task set serves every task of the pool, and leaves the excluded page IDs
	 * to the search backend.
	 */
	public function testTopicTaskSetIsUnchanged() {
		$pool = $this->newTopicPool( 'arts', 10 );
		$pageIds = array_combine( $this->titlesOf( $pool ), range( 101, 110 ) );

		$taskSet = $this->newCacheDecorator( new StaticTaskSuggester( $pool ), $pageIds )->suggest(
			$this->user, new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] ), null, null,
			[ 'excludePageIds' => [ 101, 102 ] ]
		);

		$this->assertSame( 10, $taskSet->getTotalCount() );
		$this->assertArrayEquals( $this->titlesOf( $pool ), $this->titlesOf( $taskSet ) );
	}

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param array $pageIds Title text => page ID, for the link batch.
	 * @param callable|null $shuffleList Replaces the shuffle, so that the served order is
	 *   known. Defaults to the real random shuffle.
	 */
	private function newCacheDecorator(
		TaskSuggester $taskSuggester,
		array $pageIds = [],
		?callable $shuffleList = null
	): CacheDecorator {
		$jobQueueGroup = $this->createNoOpMock( JobQueueGroup::class, [ 'lazyPush' ] );
		$jobQueueGroup->method( 'lazyPush' )->willReturnCallback(
			function ( $job ) {
				$this->pushedJobs[] = $job;
			}
		);
		$args = [
			$taskSuggester,
			$jobQueueGroup,
			$this->cache,
			$this->createNoOpMock( TaskSetListener::class, [ 'run' ] ),
			new JsonCodec(),
			$this->createNoOpMock( LinkBatchFactory::class, [ 'newLinkBatch' ] ),
			$this->newTitleFactory( $pageIds ),
		];
		$args[5]->method( 'newLinkBatch' )->willReturn(
			$this->createNoOpMock( LinkBatch::class, [ 'setCaller', 'execute' ] )
		);
		if ( !$shuffleList ) {
			return new CacheDecorator( ...$args );
		}
		$cacheDecorator = $this->getMockBuilder( CacheDecorator::class )
			->setConstructorArgs( $args )
			->onlyMethods( [ 'shuffleList' ] )
			->getMock();
		$cacheDecorator->method( 'shuffleList' )->willReturnCallback( $shuffleList );
		return $cacheDecorator;
	}

	/**
	 * @param array $pageIds Title text => page ID. Titles which are not listed have no ID.
	 */
	private function newTitleFactory( array $pageIds ): TitleFactory {
		$titleFactory = $this->createNoOpMock( TitleFactory::class, [ 'newFromLinkTarget' ] );
		$titleFactory->method( 'newFromLinkTarget' )->willReturnCallback(
			function ( $linkTarget ) use ( $pageIds ) {
				$title = $this->createNoOpMock( Title::class, [ 'getArticleID' ] );
				$title->method( 'getArticleID' )->willReturn( $pageIds[$linkTarget->getDBkey()] ?? 0 );
				return $title;
			}
		);
		return $titleFactory;
	}

	/**
	 * @param array $tasksPerInterest Interest => number of tasks similar to it.
	 * @return Task[]
	 */
	private function newInterestPool( array $tasksPerInterest ): array {
		$tasks = [];
		foreach ( $tasksPerInterest as $interest => $count ) {
			$tasks = array_merge( $tasks, $this->newPool(
				new InterestBasedTopic( $interest, new TitleValue( NS_MAIN, $interest ) ), $count
			) );
		}
		return $tasks;
	}

	/**
	 * @param iterable<Task> $tasks A TaskSet or a list of tasks.
	 * @return string[]
	 */
	private function interestsOf( iterable $tasks ): array {
		$interests = [];
		foreach ( $tasks as $task ) {
			$interests[] = $task->getTopics()[0]->getId();
		}
		return $interests;
	}

	/**
	 * A task suggester which records the limit and the options of every suggest() call.
	 * @return TaskSuggester&object{calls: array[]}
	 */
	private function newRecordingTaskSuggester() {
		return new class implements TaskSuggester {
			/** @var array[] One entry per call, with the keys 'limit' and 'options'. */
			public array $calls = [];

			/** @inheritDoc */
			public function suggest(
				UserIdentity $user,
				TaskSetFilters $taskSetFilters,
				?int $limit = null,
				?int $offset = null,
				array $options = []
			) {
				$this->calls[] = [ 'limit' => $limit, 'options' => $options ];
				return new TaskSet(
					[ new Task( new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ),
						new TitleValue( NS_MAIN, 'Foo' ) ) ],
					1, 0, $taskSetFilters
				);
			}

			/** @inheritDoc */
			public function filter( UserIdentity $user, TaskSet $taskSet ) {
				return $taskSet;
			}
		};
	}

	/**
	 * Build $count tasks about one topic, titled after it.
	 * @return Task[]
	 */
	private function newTopicPool( string $topicId, int $count ): array {
		return $this->newPool( new Topic( $topicId ), $count );
	}

	/**
	 * Build $count tasks about one topic, titled after it.
	 * @return Task[]
	 */
	private function newPool( Topic $topic, int $count ): array {
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$tasks = [];
		for ( $i = 1; $i <= $count; $i++ ) {
			$task = new Task( $taskType, new TitleValue( NS_MAIN, $topic->getId() . "-$i" ) );
			$task->setTopics( [ $topic ] );
			$tasks[] = $task;
		}
		return $tasks;
	}

	/**
	 * @param iterable<Task> $tasks A TaskSet or a list of tasks.
	 * @return string[]
	 */
	private function titlesOf( iterable $tasks ): array {
		$titles = [];
		foreach ( $tasks as $task ) {
			$titles[] = $task->getTitle()->getDBkey();
		}
		return $titles;
	}

}
