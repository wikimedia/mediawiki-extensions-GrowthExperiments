<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
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
			->setMethods( [ 'getWikiText' ] )
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
	protected function getConfigurationLoader( $taskTypes, $topics ) {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->disableOriginalConstructor()
			->setMethods( [ 'loadTaskTypes', 'loadTopics', 'setMessageLocalizer' ] )
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

}
