<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentityValue;
use SearchEngineFactory;
use StatusValue;
use Wikimedia\Stats\NullStatsdDataFactory;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class LocalSearchTaskSuggesterFactoryTest extends SearchTaskSuggesterFactoryTestBase {

	/**
	 * @dataProvider provideCreate
	 */
	public function testCreate( $taskTypes, array $topics, ?string $expectedError ) {
		if ( $taskTypes === 'error' && $expectedError === 'error' ) {
			$error = $this->createNoOpMock( Status::class, [ 'getWikiText' ] );
			$error->method( 'getWikiText' )->willReturn( 'foo' );
			$taskTypes = $error;
			$expectedError = $error;
		}

		$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory(
			$this->createMock( TaskTypeHandlerRegistry::class ),
			$this->getNewcomerTasksConfigurationLoader( $taskTypes ),
			$this->createNoOpMock( SearchStrategy::class ),
			$this->createNoOpMock( NewcomerTasksUserOptionsLookup::class ),
			$this->createNoOpMock( SearchEngineFactory::class ),
			$this->createNoOpMock( LinkBatchFactory::class ),
			StatsFactory::newNull(),
			new NullStatsdDataFactory(),
			$this->getTopicRegistry( $topics )
		);
		$taskSuggester = $taskSuggesterFactory->create();
		if ( $expectedError ) {
			$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
			$error = $taskSuggester->suggest( new UserIdentityValue( 1, 'Foo' ), new TaskSetFilters() );
			$this->assertInstanceOf( StatusValue::class, $error );
			$this->assertSame( $expectedError, $error );
		} else {
			$this->assertInstanceOf( LocalSearchTaskSuggester::class, $taskSuggester );
		}
	}

}
