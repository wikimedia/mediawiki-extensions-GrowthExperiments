<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\RawOresTopic;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator
 */
class TopicDecoratorTest extends MediaWikiIntegrationTestCase {

	public function testUseOresTopics() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$taskTypes = [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ),
		];
		$configurationLoader = new TopicDecorator(
			$this->getConfigurationLoaderMock( $taskTypes ),
			new StaticTopicRegistry( [ new Topic( 'topic1' ) ] ),
			true,
			[]
		);
		$this->assertArrayEquals( $configurationLoader->loadTaskTypes(), $taskTypes );
		foreach ( $configurationLoader->getTopics() as $topic ) {
			$this->assertTrue( $topic instanceof RawOresTopic );
		}
	}

	private function getConfigurationLoaderMock( $taskTypes = [] ): ConfigurationLoader {
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		return $configurationLoader;
	}

}
