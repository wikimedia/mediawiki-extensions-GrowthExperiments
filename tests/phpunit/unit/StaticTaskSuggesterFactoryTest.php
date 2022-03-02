<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Status;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory
 */
class StaticTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	public function testCreate() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$task = new Task( $taskType, new TitleValue( NS_MAIN, 'Task' ) );

		$suggester = new StaticTaskSuggester( [ $task ] );
		$factory = new StaticTaskSuggesterFactory( $suggester );
		$this->assertSame( $suggester, $factory->create() );

		$factory = new StaticTaskSuggesterFactory( [ $task ] );
		$suggester = $factory->create();
		$this->assertInstanceOf( StaticTaskSuggester::class, $suggester );
		$this->assertSame( [ $task ], iterator_to_array( $suggester->suggest( $user, new TaskSetFilters() ) ) );

		$error = $this->getMockBuilder( Status::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'getWikiText' ] )
			->getMock();
		// avoid triggering service loading when the factory tries to log the error
		$error->method( 'getWikiText' )->willReturn( 'Test error' );
		/** @var Status $error */
		$factory = new StaticTaskSuggesterFactory( $error );
		$suggester = $factory->create();
		$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $suggester );
		$this->assertSame( $error, $suggester->suggest( $user, new TaskSetFilters() ) );
	}

}
