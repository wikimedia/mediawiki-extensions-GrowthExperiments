<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use IBufferingStatsdDataFactory;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use SearchEngineFactory;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class LocalSearchTaskSuggesterFactoryTest extends SearchTaskSuggesterFactoryTest {

	/**
	 * @dataProvider provideCreate
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param StatusValue|null $expectedError
	 */
	public function testCreate( $taskTypes, $topics, $expectedError ) {
		$taskTypeHandlerRegistry = $this->getTaskTypeHandlerRegistry();
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $taskTypes, $topics );
		$searchStrategy = $this->getSearchStrategy();
		$newcomerTasksUserOptionsLookup = $this->getNewcomerTasksUserOptionsLookup();
		$searchEngineFactory = $this->getSearchEngineFactory();
		$linkBatchFactory = $this->getLinkBatchFactory();
		$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory( $taskTypeHandlerRegistry,
			$configurationLoader, $searchStrategy, $newcomerTasksUserOptionsLookup,
			$searchEngineFactory, $linkBatchFactory, $this->getStatsdFactory() );
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

	/**
	 * @return TaskTypeHandlerRegistry|MockObject
	 */
	private function getTaskTypeHandlerRegistry() {
		return $this->createMock( TaskTypeHandlerRegistry::class );
	}

	/**
	 * @return SearchEngineFactory|MockObject
	 */
	private function getSearchEngineFactory() {
		return $this->createNoOpMock( SearchEngineFactory::class );
	}

	/**
	 * @return LinkBatchFactory|MockObject
	 */
	private function getLinkBatchFactory() {
		return $this->createNoOpMock( LinkBatchFactory::class );
	}

	/**
	 * @return IBufferingStatsdDataFactory|MockObject
	 */
	private function getStatsdFactory() {
		return $this->createMock( IBufferingStatsdDataFactory::class );
	}

}
