<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory
 */
class StaticTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	public function testCreate() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$task = new Task( $taskType, new TitleValue( NS_MAIN, 'Task' ) );

		$suggester = new StaticTaskSuggester( [ $task ] );
		$factory = new StaticTaskSuggesterFactory( $suggester, $this->createNoOpMock( StatusFormatter::class ) );
		$this->assertSame( $suggester, $factory->create() );

		$factory = new StaticTaskSuggesterFactory( [ $task ], $this->createNoOpMock( StatusFormatter::class ) );
		$suggester = $factory->create();
		$this->assertInstanceOf( StaticTaskSuggester::class, $suggester );
		$this->assertSame( [ $task ], iterator_to_array( $suggester->suggest( $user, new TaskSetFilters() ) ) );

		$error = StatusValue::newFatal( 'june' );
		$statusFormatterWithErrorMock = $this->createNoOpMock( StatusFormatter::class, [ 'getWikiText' ] );
		$statusFormatterWithErrorMock->expects( $this->once() )
			->method( 'getWikiText' )
			->with( $error, [ 'lang' => 'en' ] )
			->willReturn( '' );
		$factory = new StaticTaskSuggesterFactory( $error, $statusFormatterWithErrorMock );
		$suggester = $factory->create();
		$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $suggester );
		$this->assertSame( $error, $suggester->suggest( $user, new TaskSetFilters() ) );
	}

}
