<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
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
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @param StatusValue|null $expectedError
	 */
	public function testCreate( $taskTypes, $topics, $templateBlacklist, $expectedError ) {
		$configurationLoader = $this->getConfigurationLoader( $taskTypes, $topics, $templateBlacklist );
		$searchStrategy = $this->getSearchStrategy();
		$templateProvider = $this->getTemplateProvider();
		$searchEngineFactory = $this->getSearchEngineFactory();
		$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory( $configurationLoader,
			$searchStrategy, $templateProvider, $searchEngineFactory );
		$taskSuggester = $taskSuggesterFactory->create();
		if ( $expectedError ) {
			$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
			$error = $taskSuggester->suggest( new UserIdentityValue( 1, 'Foo', 1 ) );
			$this->assertInstanceOf( StatusValue::class, $error );
			$this->assertSame( $expectedError, $error );
		} else {
			$this->assertInstanceOf( LocalSearchTaskSuggester::class, $taskSuggester );
		}
	}

	/**
	 * @return SearchEngineFactory|MockObject
	 */
	private function getSearchEngineFactory() {
		return $this->createNoOpMock( SearchEngineFactory::class );
	}

}
