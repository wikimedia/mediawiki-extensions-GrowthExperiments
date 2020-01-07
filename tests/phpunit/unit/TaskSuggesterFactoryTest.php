<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SearchEngineFactory;
use Status;
use StatusValue;
use TitleFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class TaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideCreate
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @param StatusValue|null $expectedError
	 */
	public function testCreateRemote( $taskTypes, $topics, $templateBlacklist, $expectedError ) {
		$configurationLoader = $this->getConfigurationLoader( $taskTypes, $topics, $templateBlacklist );
		$templateProvider = $this->getTemplateProvider();
		$requestFactory = $this->getRequestFactory();
		$titleFactory = $this->getTitleFactory();
		$apiUrl = 'https://example.com';
		$taskSuggesterFactory = new TaskSuggesterFactory( $configurationLoader );
		$taskSuggester = $taskSuggesterFactory->createRemote( $templateProvider, $requestFactory,
			$titleFactory, $apiUrl );
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
	 * @dataProvider provideCreate
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @param StatusValue|null $expectedError
	 */
	public function testCreateLocal( $taskTypes, $topics, $templateBlacklist, $expectedError ) {
		$configurationLoader = $this->getConfigurationLoader( $taskTypes, $topics, $templateBlacklist );
		$searchEngineFactory = $this->getSearchEngineFactory();
		$templateProvider = $this->getTemplateProvider();
		$taskSuggesterFactory = new TaskSuggesterFactory( $configurationLoader );
		$taskSuggester = $taskSuggesterFactory->createLocal( $searchEngineFactory, $templateProvider );
		if ( $expectedError ) {
			$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
			$error = $taskSuggester->suggest( new UserIdentityValue( 1, 'Foo', 1 ) );
			$this->assertInstanceOf( StatusValue::class, $error );
			$this->assertSame( $expectedError, $error );
		} else {
			$this->assertInstanceOf( LocalSearchTaskSuggester::class, $taskSuggester );
		}
	}

	public function provideCreate() {
		$error = $this->getMockBuilder( Status::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getWikiText' ] )
			->getMock();
		$error->method( 'getWikiText' )->willReturn( 'foo' );
		return [
			'success' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [] ),
				],
				'topics' => [ new Topic( 't' ) ],
				'templateBlacklist' => [],
				'expectedError' => null,
			],
			'tasktype error' => [
				'taskTypes' => $error,
				'topics' => [ new Topic( 't' ) ],
				'templateBlacklist' => [],
				'expectedError' => $error,
			],
			'topic error' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [] ),
				],
				'topics' => $error,
				'templateBlacklist' => [],
				'expectedError' => $error,
			],
			'template blacklist error' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [] ),
				],
				'topics' => [ new Topic( 't' ) ],
				'templateBlacklist' => $error,
				'expectedError' => $error,
			],
		];
	}

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @return ConfigurationLoader|MockObject
	 */
	private function getConfigurationLoader( $taskTypes, $topics, $templateBlacklist ) {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->disableOriginalConstructor()
			->setMethods( [ 'loadTaskTypes', 'loadTopics', 'loadTemplateBlacklist', 'setMessageLocalizer' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		$configurationLoader->method( 'loadTopics' )->willReturn( $topics );
		$configurationLoader->method( 'loadTemplateBlacklist' )->willReturn( $templateBlacklist );
		return $configurationLoader;
	}

	/**
	 * @return TemplateProvider|MockObject
	 */
	private function getTemplateProvider() {
		$templateProvider = $this->getMockBuilder( TemplateProvider::class )
			->disableOriginalConstructor()
			->setMethods( [ 'fill' ] )
			->getMock();
		return $templateProvider;
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
	 * @return SearchEngineFactory|MockObject
	 */
	private function getSearchEngineFactory() {
		return $this->createNoOpMock( SearchEngineFactory::class );
	}

}
