<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;

abstract class SearchTaskSuggesterFactoryTestBase extends MediaWikiUnitTestCase {

	public static function provideCreate() {
		return [
			'success' => [
				'taskTypes' => [
					new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [], [], [] ),
				],
				'topics' => [ new Topic( 't' ) ],
				'expectedError' => null,
			],
			'tasktype error' => [
				'taskTypes' => 'error',
				'topics' => [ new Topic( 't' ) ],
				'expectedError' => 'error',
			],
		];
	}

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @return ConfigurationLoader|MockObject
	 */
	protected function getNewcomerTasksConfigurationLoader( $taskTypes ) {
		$loader = $this->createNoOpMock( ConfigurationLoader::class, [ 'loadTaskTypes' ] );
		$loader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		return $loader;
	}

	/**
	 * @param Topic[] $topics
	 * @return StaticTopicRegistry
	 */
	protected function getTopicRegistry( array $topics ) {
		return new StaticTopicRegistry( $topics );
	}

}
