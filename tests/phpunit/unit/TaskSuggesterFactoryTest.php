<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;
use TitleFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class TaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideCreateRemote
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @param StatusValue|null $expectedError
	 */
	public function testCreateRemote( $taskTypes, $templateBlacklist, $expectedError ) {
		$configurationLoader = $this->getConfigurationLoader( $taskTypes, $templateBlacklist );
		$requestFactory = $this->getRequestFactory();
		$titleFactory = $this->getTitleFactory();
		$apiUrl = 'https://example.com';
		$taskSuggesterFactory = new TaskSuggesterFactory( $configurationLoader );
		$taskSuggester = $taskSuggesterFactory->createRemote( $requestFactory, $titleFactory, $apiUrl );
		if ( $expectedError ) {
			$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
			$error = $taskSuggester->suggest( new UserIdentityValue( 1, 'Foo', 1 ) );
			$this->assertInstanceOf( StatusValue::class, $error );
			$this->assertSame( $expectedError, $error );
		} else {
			$this->assertInstanceOf( RemoteSearchTaskSuggester::class, $taskSuggester );
		}
	}

	public function provideCreateRemote() {
		$error = $this->getMockBuilder( Status::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getWikiText' ] )
			->getMock();
		$error->method( 'getWikiText' )->willReturn( 'foo' );
		return [
			'success' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [] ),
				],
				'templateBlacklist' => [],
				'expectedError' => null,
			],
			'tasktype error' => [
				'taskTypes' => $error,
				'templateBlacklist' => [],
				'expectedError' => $error,
			],
			'template blacklist error' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [] ),
				],
				'templateBlacklist' => $error,
				'expectedError' => $error,
			],
		];
	}

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param LinkTarget[]|StatusValue $templateBlacklist
	 * @return ConfigurationLoader|MockObject
	 */
	private function getConfigurationLoader( $taskTypes, $templateBlacklist ) {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->disableOriginalConstructor()
			->setMethods( [ 'loadTaskTypes', 'loadTemplateBlacklist', 'setMessageLocalizer' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		$configurationLoader->method( 'loadTemplateBlacklist' )->willReturn( $templateBlacklist );
		return $configurationLoader;
	}

	/**
	 * @return HttpRequestFactory|MockObject
	 */
	private function getRequestFactory() {
		return $this->createMock( HttpRequestFactory::class );
	}

	/**
	 * @return TitleFactory|MockObject
	 */
	private function getTitleFactory() {
		return $this->createMock( TitleFactory::class );
	}

}
