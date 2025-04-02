<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
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

		$configurationLoader = new StaticConfigurationLoader( $taskTypes );
		$this->assertSame( $expectedTaskTypes, $configurationLoader->getTaskTypes() );

		$configurationLoader = new StaticConfigurationLoader( StatusValue::newFatal( 'foo' ), [] );
		$this->assertSame( [], $configurationLoader->getTaskTypes() );
	}
}
