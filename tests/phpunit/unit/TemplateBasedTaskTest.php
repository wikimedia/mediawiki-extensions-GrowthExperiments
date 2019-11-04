<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Task\TemplateBasedTask
 */
class TemplateBasedTaskTest extends MediaWikiUnitTestCase {

	public function testGetTemplates() {
		list( $t1, $t2 ) = array_map( function ( string $t ) {
			return new TitleValue( NS_TEMPLATE, $t );
		}, [ 'T1', 'T2' ] );
		$taskType = new TemplateBasedTaskType( 'foo', TaskType::DIFFICULTY_EASY, [], [] );
		$task = new TemplateBasedTask( $taskType, new TitleValue( NS_MAIN, 'task' ) );
		$this->assertSame( [], $task->getTemplates() );
		$task->addTemplate( $t1 );
		$task->addTemplate( $t2 );
		$this->assertSame( [ $t1, $t2 ], $task->getTemplates() );
	}

}
