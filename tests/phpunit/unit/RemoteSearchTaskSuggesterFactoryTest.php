<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
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
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @param StatusValue|null $expectedError
	 */
	public function testCreate( $taskTypes, $topics, $templateBlacklist, $expectedError ) {
		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$configurationLoader = $this->getConfigurationLoader( $taskTypes, $topics, $templateBlacklist );
		$searchStrategy = $this->getSearchStrategy();
		$requestFactory = $this->getRequestFactory();
		$titleFactory = $this->getTitleFactory();
		$apiUrl = 'https://example.com';
		$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory( $taskTypeHandlerRegistry,
			$configurationLoader, $searchStrategy, $requestFactory, $titleFactory, $apiUrl );
		$taskSuggester = $taskSuggesterFactory->create();
		if ( $expectedError ) {
			$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
			$error = $taskSuggester->suggest( new UserIdentityValue( 1, 'Foo', 1 ) );
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

}
