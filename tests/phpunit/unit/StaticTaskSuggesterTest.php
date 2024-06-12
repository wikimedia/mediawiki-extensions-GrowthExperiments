<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester
 */
class StaticTaskSuggesterTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSuggest
	 */
	public function testSuggest(
		$taskSetFilters, $limit, $offset,
		$expectedTitles, $expectedTotalCount, $expectedOffset
	) {
		$user = new UserIdentityValue( 1, 'Foo' );
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$taskType3 = new TaskType( 'create', TaskType::DIFFICULTY_HARD );
		$topic1 = new Topic( 'topic1' );
		$topic2 = new Topic( 'topic2' );
		$topic3 = new Topic( 'topic3' );
		$tasks = [
			0 => new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-1' ) ),
			1 => new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-1' ) ),
			2 => new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-2' ) ),
			3 => new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-2' ) ),
			4 => new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-3' ) ),
			5 => new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-3' ) ),
			6 => new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-4' ) ),
			7 => new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-5' ) ),
			8 => new Task( $taskType3, new TitleValue( NS_MAIN, 'Create-1' ) ),
		];
		$topics = [
			2 => [ $topic1 ],
			3 => [ $topic1 ],
			4 => [ $topic2 ],
			6 => [ $topic1, $topic2 ],
			7 => [ $topic3 ],

		];
		foreach ( $tasks as $i => $task ) {
			/** @var Task $task */
			if ( isset( $topics[$i] ) ) {
				$task->setTopics( $topics[$i] );
			}
		}
		$suggester = new StaticTaskSuggester( $tasks );

		$taskSet = $suggester->suggest( $user, $taskSetFilters, $limit, $offset );
		$this->assertInstanceOf( TaskSet::class, $taskSet );
		$this->assertTaskSetEqualsTitles( $expectedTitles, $taskSet );
		$this->assertSame( $expectedTotalCount, $taskSet->getTotalCount() );
		$this->assertSame( $expectedOffset, $taskSet->getOffset() );
	}

	public static function provideSuggest() {
		return [
			'empty filters' => [
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => null,
				'offset' => null,
				'expectedTitles' => [ 'Copyedit-1', 'Link-1', 'Link-2', 'Copyedit-2',
					'Copyedit-3', 'Link-3', 'Copyedit-4', 'Copyedit-5', 'Create-1' ],
				'expectedTotalCount' => 9,
				'expectedOffset' => 0,
			],
			'limit' => [
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => 3,
				'offset' => null,
				'expectedTitles' => [ 'Copyedit-1', 'Link-1', 'Link-2' ],
				'expectedTotalCount' => 9,
				'expectedOffset' => 0,
			],
			'limit + offset' => [
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => 2,
				'offset' => 6,
				'expectedTitles' => [ 'Copyedit-4', 'Copyedit-5' ],
				'expectedTotalCount' => 9,
				'expectedOffset' => 6,
			],
			'taskfilter' => [
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit' ], [] ),
				'limit' => null,
				'offset' => null,
				'expectedTitles' => [ 'Copyedit-1', 'Copyedit-2', 'Copyedit-3', 'Copyedit-4', 'Copyedit-5' ],
				'expectedTotalCount' => 5,
				'expectedOffset' => 0,
			],
			'taskfilter + limit' => [
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit' ], [] ),
				'limit' => 4,
				'offset' => 0,
				'expectedTitles' => [ 'Copyedit-1', 'Copyedit-2', 'Copyedit-3', 'Copyedit-4' ],
				'expectedTotalCount' => 5,
				'expectedOffset' => 0,
			],
			'taskfilter + limit + offset' => [
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit' ], [] ),
				'limit' => 2,
				'offset' => 2,
				'expectedTitles' => [ 'Copyedit-3', 'Copyedit-4' ],
				'expectedTotalCount' => 5,
				'expectedOffset' => 2,
			],
			'multiple taskfilters' => [
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit', 'create' ], [] ),
				'limit' => null,
				'offset' => null,
				'expectedTitles' => [ 'Copyedit-1', 'Copyedit-2', 'Copyedit-3', 'Copyedit-4',
					'Copyedit-5', 'Create-1' ],
				'expectedTotalCount' => 6,
				'expectedOffset' => 0,
			],
			'topicfilter' => [
				'taskSetFilters' => new TaskSetFilters( [], [ 'topic1' ] ),
				'limit' => null,
				'offset' => null,
				'expectedTitles' => [ 'Link-2', 'Copyedit-2', 'Copyedit-4' ],
				'expectedTotalCount' => 3,
				'expectedOffset' => 0,
			],
			'multiple topicfilters' => [
				'taskSetFilters' => new TaskSetFilters( [], [ 'topic1', 'topic2' ] ),
				'limit' => null,
				'offset' => null,
				'expectedTitles' => [ 'Link-2', 'Copyedit-2', 'Copyedit-3', 'Copyedit-4' ],
				'expectedTotalCount' => 4,
				'expectedOffset' => 0,
			],
			'taskfilter + topicfilter' => [
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit' ], [ 'topic1' ] ),
				'limit' => null,
				'offset' => null,
				'expectedTitles' => [ 'Copyedit-2', 'Copyedit-4' ],
				'expectedTotalCount' => 2,
				'expectedOffset' => 0,
			],
			'taskfilter + topicfilter + limit/offset' => [
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit' ], [ 'topic1', 'topic2' ] ),
				'limit' => 1,
				'offset' => 1,
				'expectedTitles' => [ 'Copyedit-3' ],
				'expectedTotalCount' => 3,
				'expectedOffset' => 1,
			],
		];
	}

	/**
	 * @param string[] $expectedTitles
	 * @param TaskSet $taskSet
	 */
	protected function assertTaskSetEqualsTitles( array $expectedTitles, TaskSet $taskSet ) {
		$actualTitles = array_map( static function ( Task $task ) {
			return $task->getTitle()->getText();
		}, iterator_to_array( $taskSet ) );
		$this->assertSame( $expectedTitles, $actualTitles );
	}

}
