<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use TitleFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class RemoteSearchTaskSuggesterFactoryTest extends SearchTaskSuggesterFactoryTest {

	/**
	 * @dataProvider provideCreate
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param StatusValue|null $expectedError
	 */
	public function testCreate( $taskTypes, $topics, $expectedError ) {
		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $taskTypes, $topics );
		$searchStrategy = $this->getSearchStrategy();
		$newcomerTasksUserOptionsLookup = $this->getNewcomerTasksUserOptionsLookup();
		$requestFactory = $this->getRequestFactory();
		$titleFactory = $this->getTitleFactory();
		$linkBatchFactory = $this->getLinkBatchFactory();
		$apiUrl = 'https://example.com';
		$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory( $taskTypeHandlerRegistry,
			$configurationLoader, $searchStrategy, $newcomerTasksUserOptionsLookup, $requestFactory,
			$titleFactory, $linkBatchFactory, $apiUrl );
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

	/**
	 * @return HttpRequestFactory|MockObject
	 */
	private function getRequestFactory() {
		return $this->createNoOpMock( HttpRequestFactory::class );
	}

	/**
	 * @return TitleFactory|MockObject
	 */
	private function getTitleFactory() {
		return $this->createNoOpMock( TitleFactory::class );
	}

	/**
	 * @return LinkBatchFactory|MockObject
	 */
	private function getLinkBatchFactory() {
		return $this->createNoOpMock( LinkBatchFactory::class );
	}

}
