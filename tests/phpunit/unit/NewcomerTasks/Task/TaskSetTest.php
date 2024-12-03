<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use InvalidArgumentException;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

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

	/**
	 * @covers ::toJsonArray
	 * @covers ::newFromJsonArray
	 */
	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		$taskSet = new TaskSet(
			[
				new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
				new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
				new Task( $taskType, new TitleValue( NS_MAIN, 'Baz' ) ),
			],
			30,
			1,
			new TaskSetFilters( [ 'x' ], [ 'y' ] ),
			[
				new Task( $taskType, new TitleValue( NS_PROJECT, 'Boom' ) ),
			]
		);
		$taskSet->setQualityGateConfigForTaskType( 'foo', [ 'gate' => 'value' ] );
		$taskSet2 = $codec->deserialize( $codec->serialize( $taskSet ) );
		$this->assertEquals( $taskSet, $taskSet2 );
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
