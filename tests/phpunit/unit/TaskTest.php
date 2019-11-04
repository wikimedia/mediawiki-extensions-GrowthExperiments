<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Task\Task
 */
class TaskTest extends MediaWikiUnitTestCase {

	public function testTask() {
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		$task = new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) );
		$this->assertTrue( $task->getTitle()->getNamespace() === NS_MAIN );
		$this->assertTrue( $task->getTitle()->getText() === 'Foo' );
		$this->assertSame( $taskType, $task->getTaskType() );
	}

}
