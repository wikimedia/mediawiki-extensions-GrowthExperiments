<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;

abstract class SearchTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	public function provideCreate() {
		$error = $this->createNoOpMock( Status::class, [ 'getWikiText' ] );
		$error->method( 'getWikiText' )->willReturn( 'foo' );
		return [
			'success' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [], [] ),
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
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [], [] ),
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
		$loader = $this->createNoOpMock( ConfigurationLoader::class, [ 'loadTaskTypes', 'loadTopics' ] );
		$loader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		$loader->method( 'loadTopics' )->willReturn( $topics );
		return $loader;
	}

}
