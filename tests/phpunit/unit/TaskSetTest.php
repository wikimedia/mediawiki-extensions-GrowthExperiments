<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use InvalidArgumentException;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\Task\TaskSet
 */
class TaskSetTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getIterator
	 * @covers ::getOffset
	 * @covers ::getTotalCount
	 */
	public function testTaskType() {
		$taskSet = $this->getTaskSet();
		$pages = array_map( static function ( Task $task ) {
			return $task->getTitle()->getText();
		}, iterator_to_array( $taskSet ) );
		$this->assertSame( [ 'Foo', 'Bar', 'Baz' ], $pages );
		$this->assertSame( 'Foo', $taskSet[0]->getTitle()->getDBkey() );
		$this->assertSame( 3, $taskSet->getTotalCount() );
		$this->assertSame( 1, $taskSet->getOffset() );
	}

	/**
	 * @covers ::__construct
	 */
	public function testInvalidParameter() {
		$this->expectException( InvalidArgumentException::class );

		new TaskSet( [ new TitleValue( NS_MAIN, 'Foo' ) ], 1, 0, new TaskSetFilters() );
	}

	private function getTaskSet(): TaskSet {
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		return new TaskSet( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Baz' ) ),
		], 3, 1, new TaskSetFilters() );
	}

}
