<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use InvalidArgumentException;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Task\TaskSet
 */
class TaskSetTest extends MediaWikiUnitTestCase {

	public function testTaskType() {
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		$taskSet = new TaskSet( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Baz' ) ),
		], 3, 1 );
		$pages = array_map( function ( Task $task ) {
			return $task->getTitle()->getText();
		}, iterator_to_array( $taskSet ) );
		$this->assertSame( [ 'Foo', 'Bar', 'Baz' ], $pages );
		$this->assertSame( 'Foo', $taskSet[0]->getTitle()->getDBkey() );
		$this->assertSame( 3, $taskSet->getTotalCount() );
		$this->assertSame( 1, $taskSet->getOffset() );
	}

	public function testInvalidParameter() {
		$this->expectException( InvalidArgumentException::class );

		new TaskSet( [ new TitleValue( NS_MAIN, 'Foo' ) ], 1, 0 );
	}

}
