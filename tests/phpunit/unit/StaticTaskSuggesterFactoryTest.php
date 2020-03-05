<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory
 */
class StaticTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	public function testCreate() {
		$user = new UserIdentityValue( 1, 'Foo', 1 );
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$task = new Task( $taskType, new TitleValue( NS_MAIN, 'Task' ) );
		$suggester = new StaticTaskSuggester( [ $task ] );

		$factory = new StaticTaskSuggesterFactory( $suggester );
		$this->assertSame( $suggester, $factory->create() );

		$factory = new StaticTaskSuggesterFactory( [ $task ] );
		$suggester = $factory->create();
		$this->assertInstanceOf( StaticTaskSuggester::class, $suggester );
		$this->assertSame( [ $task ], iterator_to_array( $suggester->suggest( $user ) ) );
	}

}
