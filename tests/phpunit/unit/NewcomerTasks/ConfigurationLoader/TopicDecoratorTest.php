<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\RawOresTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator
 */
class TopicDecoratorTest extends MediaWikiUnitTestCase {

	public function testUseGrowthTopics() {
		$taskTypes = [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY )
		];
		$topics = [
			new Topic( 'topic1' )
		];
		$configurationLoader = new TopicDecorator(
			$this->getConfigurationLoaderMock( $taskTypes, $topics ),
			false,
			[]
		);
		$this->assertArrayEquals( $configurationLoader->loadTaskTypes(), $taskTypes );
		$this->assertArrayEquals( $configurationLoader->loadTopics(), $topics );
	}

	public function testUseGrowthTopicsWithAdditionalTaskTypes() {
		$taskTypes = [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY )
		];
		$topics = [
			new Topic( 'topic1' )
		];
		$additionalTaskTypes = [
			new TaskType( '_null', TaskType::DIFFICULTY_HARD )
		];
		$configurationLoader = new TopicDecorator(
			$this->getConfigurationLoaderMock( $taskTypes, $topics ),
			false,
			$additionalTaskTypes
		);
		$this->assertArrayEquals(
			$configurationLoader->loadTaskTypes(),
			array_merge( $taskTypes, $additionalTaskTypes )
		);
	}

	public function testUseOresTopics() {
		$taskTypes = [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY )
		];
		$configurationLoader = new TopicDecorator(
			$this->getConfigurationLoaderMock( $taskTypes, [ new Topic( 'topic1' ) ] ),
			true,
			[]
		);
		$this->assertArrayEquals( $configurationLoader->loadTaskTypes(), $taskTypes );
		foreach ( $configurationLoader->loadTopics() as $topic ) {
			$this->assertTrue( $topic instanceof RawOresTopic );
		}
	}

	public function testUseOresTopicsWithAdditionalTaskTypes() {
		$taskTypes = [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY )
		];
		$additionalTaskTypes = [
			new TaskType( '_null', TaskType::DIFFICULTY_HARD )
		];
		$configurationLoader = new TopicDecorator(
			$this->getConfigurationLoaderMock( $taskTypes, [ new Topic( 'topic1' ) ] ),
			true,
			$additionalTaskTypes
		);
		$this->assertArrayEquals(
			$configurationLoader->loadTaskTypes(),
			array_merge( $taskTypes, $additionalTaskTypes )
		);
	}

	private function getConfigurationLoaderMock( $taskTypes = [], $topics = [] ): ConfigurationLoader {
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		$configurationLoader->method( 'loadTopics' )->willReturn( $topics );
		return $configurationLoader;
	}
}
