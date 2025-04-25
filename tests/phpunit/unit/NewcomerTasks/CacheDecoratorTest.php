<?php

namespace GrowthExperiments\Tests\Unit;

use Exception;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\Stub\ReturnArgument;
use StatusValue;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator
 */
class CacheDecoratorTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSuggest
	 * @param array $calls List of arrays with:
	 *   - suggest: [ 'expect' => array, 'return' => Task[]|StatusValue ] | null
	 *   - filter: [ array $expects, Task[]|StatusValue $returns ] | null
	 *   - user: UserIdentity
	 *   - taskTypeFilter: string[]
	 *   - topicFilter: string[]
	 *   - limit: int|null
	 *   - offset: int|null
	 *   - options: array
	 * @param TaskSet|StatusValue|Exception $expectedResult
	 */
	public function testSuggest(
		array $calls,
		$expectedResult
	) {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$mockJobQueueGroup = $this->createNoOpMock( JobQueueGroup::class, [ 'lazyPush' ] );
		$mockListener = $this->createNoOpMock( TaskSetListener::class, [ 'run' ] );
		foreach ( $calls as $i => $call ) {
			$mockedMethods = [ 'suggest', 'filter' ];
			$suggester = $this->createNoOpMock( TaskSuggester::class, $mockedMethods );
			foreach ( $mockedMethods as $method ) {
				if ( $call[$method] ) {
					$suggester->expects( $this->once() )
						->method( $method )
						->with( ...$call[$method]['expect'] )
						->willReturn( $call[$method]['return'] );
				} else {
					$suggester->expects( $this->never() )
						->method( $method );
				}
			}
			if ( $expectedResult instanceof Exception && $i === count( $calls ) - 1 ) {
				$this->expectException( get_class( $expectedResult ) );
			}
			$cacheDecorator = new CacheDecorator( $suggester, $mockJobQueueGroup, $cache,
				$mockListener, new JsonCodec() );
			$result = $cacheDecorator->suggest( $call['user'], $call['taskSetFilters'],
				$call['limit'], $call['offset'], $call['options'] );
		}
		if ( !( $expectedResult instanceof Exception ) ) {
			$this->assertEquals( $expectedResult, $result );
		}
	}

	public static function provideSuggest() {
		$user = new UserIdentityValue( 1000, 'Test' );
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		// Use tasksets consisting of one task only, so we don't have to deal with randomization
		// of the task order messing up assertions.
		$taskSetFilters = new TaskSetFilters( [ 'copyedit' ], [] );
		$taskSetFilterLinks = new TaskSetFilters( [ 'links' ], [] );
		$taskSetFilterArt = new TaskSetFilters( [ 'copyedit' ], [ 'arts' ] );
		$taskset = new TaskSet( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
		], 5, 0, $taskSetFilters );
		$taskset2 = new TaskSet( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
		], 5, 0, $taskSetFilters );

		return [
			'taskset on cache miss' => [
				'calls' => [
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
				],
				'expectedResult' => $taskset,
			],
			'error on cache miss' => [
				'calls' => [
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => StatusValue::newFatal( 'error' ),
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
				],
				'expectedResult' => StatusValue::newFatal( 'error' ),
			],
			'cache hit with cached taskset' => [
				'calls' => [
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
					[
						'suggest' => null,
						'filter' => [
							'expect' => [ $user, $taskset ],
							'return' => new ReturnArgument( 1 ),
						],
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
				],
				'expectedResult' => $taskset,
			],
			'cache hit with cached error' => [
				'calls' => [
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => StatusValue::newFatal( 'error' ),
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
				],
				'expectedResult' => $taskset,
			],
			'cache miss due to task filter' => [
				'calls' => [
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilterLinks, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset2,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilterLinks,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
				],
				'expectedResult' => $taskset2,
			],
			'cache miss due to topic filter' => [
				'calls' => [
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilters, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilters,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
					[
						'suggest' => [
							'expect' => [ $user, $taskSetFilterArt, 15, 0, [ 'excludePageIds' => [] ] ],
							'return' => $taskset2,
						],
						'filter' => null,
						'user' => $user,
						'taskSetFilters' => $taskSetFilterArt,
						'limit' => 15,
						'offset' => 0,
						'options' => [],
					],
				],
				'expectedResult' => $taskset2,
			],
		];
	}

}
