<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader
 */
class StaticConfigurationLoaderTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getTaskTypes
	 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait::getTaskTypes
	 */
	public function testGetTaskTypes() {
		$copyedit = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$link = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$taskTypes = [ $copyedit, $link ];
		$expectedTaskTypes = [ 'copyedit' => $copyedit, 'link' => $link ];

		$configurationLoader = new StaticConfigurationLoader( $taskTypes, [] );
		$this->assertSame( $expectedTaskTypes, $configurationLoader->getTaskTypes() );

		$configurationLoader = new StaticConfigurationLoader( StatusValue::newFatal( 'foo' ), [] );
		$this->assertSame( [], $configurationLoader->getTaskTypes() );
	}

	/**
	 * @covers ::getTopics
	 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait::getTopics
	 */
	public function testGetTopics() {
		$topic1 = new Topic( 'topic1' );
		$topic2 = new Topic( 'topic2' );
		$topics = [ $topic1, $topic2 ];
		$expectedTopics = [ 'topic1' => $topic1, 'topic2' => $topic2 ];

		$configurationLoader = new StaticConfigurationLoader( [], $topics );
		$this->assertSame( $expectedTopics, $configurationLoader->getTopics() );

		$configurationLoader = new StaticConfigurationLoader( [], StatusValue::newFatal( 'foo' ) );
		$this->assertSame( [], $configurationLoader->getTopics() );
	}

}
