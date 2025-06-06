<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\RawOresTopic;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use StatusValue;

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
			$this->getConfigurationLoaderMock( $taskTypes ),
			$this->getTopicRegistryMock( $topics ),
			false,
			[]
		);
		$this->assertArrayEquals( $configurationLoader->loadTaskTypes(), $taskTypes );
		$this->assertArrayEquals( $configurationLoader->getTopics(), $topics );
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
			$this->getConfigurationLoaderMock( $taskTypes ),
			$this->getTopicRegistryMock( $topics ),
			false,
			$additionalTaskTypes,
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
			$this->getConfigurationLoaderMock( $taskTypes ),
			$this->getTopicRegistryMock( [ new Topic( 'topic1' ) ] ),
			true,
			[]
		);
		$this->assertArrayEquals( $configurationLoader->loadTaskTypes(), $taskTypes );
		foreach ( $configurationLoader->getTopics() as $topic ) {
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
			$this->getConfigurationLoaderMock( $taskTypes ),
			$this->getTopicRegistryMock( [ new Topic( 'topic1' ) ] ),
			true,
			$additionalTaskTypes
		);
		$this->assertArrayEquals(
			$configurationLoader->loadTaskTypes(),
			array_merge( $taskTypes, $additionalTaskTypes )
		);
	}

	private function getConfigurationLoaderMock( $taskTypes = [] ): ConfigurationLoader {
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( $taskTypes );
		return $configurationLoader;
	}

	/**
	 * @param Topic[]|StatusValue $topics
	 * @return ITopicRegistry
	 */
	protected function getTopicRegistryMock( $topics = [] ) {
		return new StaticTopicRegistry( $topics );
	}
}
