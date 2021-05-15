<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;

abstract class SearchTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	public function provideCreate() {
		$error = $this->getMockBuilder( Status::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getWikiText' ] )
			->getMock();
		$error->method( 'getWikiText' )->willReturn( 'foo' );
		return [
			'success' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [] ),
				],
				'topics' => [ new Topic( 't' ) ],
				'expectedError' => null,
			],
			'tasktype error' => [
				'taskTypes' => $error,
				'topics' => [ new Topic( 't' ) ],
				'expectedError' => $error,
			],
			'topic error' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [] ),
				],
				'topics' => $error,
				'expectedError' => $error,
			],
		];
	}

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @return ConfigurationLoader|MockObject
	 */
	protected function getNewcomerTasksConfigurationLoader( $taskTypes, $topics ) {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'loadTaskTypes', 'loadTopics' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		$configurationLoader->method( 'loadTopics' )->willReturn( $topics );
		return $configurationLoader;
	}

	/**
	 * @return SearchStrategy|MockObject
	 */
	protected function getSearchStrategy() {
		return $this->createNoOpMock( SearchStrategy::class );
	}

	/**
	 * @return NewcomerTasksUserOptionsLookup|MockObject
	 */
	protected function getNewcomerTasksUserOptionsLookup() {
		return $this->createNoOpMock( NewcomerTasksUserOptionsLookup::class );
	}

}
