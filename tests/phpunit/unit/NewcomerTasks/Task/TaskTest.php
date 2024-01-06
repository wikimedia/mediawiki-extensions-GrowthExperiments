<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

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
		$this->assertSame( [], $task->getTopics() );

		$topics = [ new Topic( 'a' ), new Topic( 'b' ) ];
		$task->setTopics( $topics );
		$this->assertSame( $topics, $task->getTopics() );
		$this->assertSame( [ 'a' => 0, 'b' => 0 ], $task->getTopicScores() );

		$topics = [ new Topic( 'a' ), new Topic( 'b' ), new Topic( 'c' ) ];
		$task->setTopics( $topics, [ 'b' => 1.5, 'a' => 0.5, 'd' => 1.1 ] );
		$this->assertSame( $topics, $task->getTopics() );
		$this->assertSame( [ 'a' => 0.5, 'b' => 1.5, 'c' => 0 ], $task->getTopicScores() );
	}

	public function testJsonSerialization() {
		// JsonCodec isn't stable to construct but there is not better way in a unit test.
		$codec = new JsonCodec();
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		$task = new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) );
		$topics = [ new Topic( 'a' ), new Topic( 'b' ), new Topic( 'c' ) ];
		$task->setTopics( $topics, [ 'b' => 1.5, 'a' => 0.5, 'c' => 1.1 ] );
		$task2 = $codec->unserialize( $codec->serialize( $task ) );
		$this->assertEquals( $task, $task2 );
	}

}
