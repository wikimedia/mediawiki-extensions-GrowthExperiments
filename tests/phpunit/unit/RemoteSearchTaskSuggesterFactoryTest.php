<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class RemoteSearchTaskSuggesterFactoryTest extends SearchTaskSuggesterFactoryTestBase {

	/**
	 * @dataProvider provideCreate
	 */
	public function testCreate( $taskTypes, $topics, $expectedError ) {
		$statusFormatterFactory = $this->createNoOpMock( StatusFormatter::class );

		if ( $taskTypes === 'error' && $expectedError === 'error' ) {
			$error = StatusValue::newFatal( 'foo' );
			$taskTypes = $error;
			$expectedError = $error;

			$statusFormatterFactory = $this->createNoOpMock( StatusFormatter::class, [ 'getWikiText' ] );
			$statusFormatterFactory->expects( $this->once() )
				->method( 'getWikiText' )
				->with( $expectedError )
				->willReturn( 'foo' );
		}

		$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory(
			$this->createMock( TaskTypeHandlerRegistry::class ),
			$this->getNewcomerTasksConfigurationLoader( $taskTypes ),
			$this->createNoOpMock( SearchStrategy::class ),
			$this->createNoOpMock( NewcomerTasksUserOptionsLookup::class ),
			$this->createNoOpMock( HttpRequestFactory::class ),
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( LinkBatchFactory::class ),
			$statusFormatterFactory,
			'https://example.com',
			$this->getTopicRegistry( $topics ),
		);
		$taskSuggester = $taskSuggesterFactory->create();
		if ( $expectedError ) {
			$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
			$error = $taskSuggester->suggest( new UserIdentityValue( 1, 'Foo' ), new TaskSetFilters() );
			$this->assertInstanceOf( StatusValue::class, $error );
			$this->assertSame( $expectedError, $error );
		} else {
			$this->assertInstanceOf( RemoteSearchTaskSuggester::class, $taskSuggester );
		}
	}

}
