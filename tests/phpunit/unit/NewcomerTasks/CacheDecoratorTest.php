<?php

namespace GrowthExperiments\Tests\Unit;

use Exception;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
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
	 * - suggester TaskSuggester
	 * - args array: Arguments to TaskSuggester::suggest()
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
			if ( $expectedResult instanceof Exception && $i === count( $calls ) - 1 ) {
				$this->expectException( get_class( $expectedResult ) );
			}
			$cacheDecorator = new CacheDecorator(
				$call['suggester'],
				$mockJobQueueGroup,
				$cache,
				$mockListener,
				new JsonCodec()
			);
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

		$taskA = new Task( $copyeditType, new TitleValue( NS_MAIN, 'Foo' ) );
		$taskB = new Task( $linksType, new TitleValue( NS_MAIN, 'Bar' ) );
		$taskC = new Task( $copyeditType, new TitleValue( NS_MAIN, 'Foo' ) );
		$taskC->setTopics( [ new Topic( 'arts' ) ] );

		$suggesterA = new StaticTaskSuggester( [ $taskA ] );
		$suggesterB = new StaticTaskSuggester( [ $taskB ] );
		$suggesterC = new StaticTaskSuggester( [ $taskC ] );

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
		];
	}

}
