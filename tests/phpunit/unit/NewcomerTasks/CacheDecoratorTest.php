<?php

namespace GrowthExperiments\Tests\Unit;

use Exception;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator
 */
class CacheDecoratorTest extends MediaWikiUnitTestCase {

	private WANObjectCache $cache;
	private UserIdentityValue $user;

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->user = new UserIdentityValue( 1000, 'Test' );
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
	 * are two separate numbers. The filters of the task set decide the size of the pool, the
	 * requested limit does not.
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
		];
	}

	/**
	 * A request for more tasks than the pool holds cannot be served from the cache. The
	 * suggested edits module asks for DEFAULT_LIMIT plus a lookahead, so the size of the pool
	 * decides if the module reads the cache or searches again.
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
		return [
			'topics, inside the pool' => [ $topics, SearchTaskSuggester::DEFAULT_LIMIT, 1 ],
			'topics, above the pool' => [ $topics, SearchTaskSuggester::DEFAULT_LIMIT + 1, 2 ],
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
	 * A topic-based task set serves every task of the pool.
	 */
	public function testTopicTaskSetIsUnchanged() {
		$pool = $this->newPool( new Topic( 'arts' ), 10 );

		$taskSet = $this->newCacheDecorator( new StaticTaskSuggester( $pool ) )->suggest(
			$this->user, new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] )
		);

		$this->assertSame( 10, $taskSet->getTotalCount() );
		$this->assertArrayEquals( $this->titlesOf( $pool ), $this->titlesOf( $taskSet ) );
	}

	private function newCacheDecorator( TaskSuggester $taskSuggester ): CacheDecorator {
		return new CacheDecorator(
			$taskSuggester,
			$this->createNoOpMock( JobQueueGroup::class, [ 'lazyPush' ] ),
			$this->cache,
			$this->createNoOpMock( TaskSetListener::class, [ 'run' ] ),
			new JsonCodec()
		);
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
