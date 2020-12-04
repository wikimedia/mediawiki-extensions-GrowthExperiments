<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Tracker\Tracker;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentityValue;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory
 */
class TrackerFactoryTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( TrackerFactory::class,
			new TrackerFactory(
				new \EmptyBagOStuff(),
				$this->createNoOpMock( ConfigurationLoader::class ),
				new \TitleFactory(),
				LoggerFactory::getInstance( 'test' ) )
		);
	}

	/**
	 * @covers ::getTracker
	 */
	public function testGetTracker() {
		$factory = new TrackerFactory(
			new \EmptyBagOStuff(),
			$this->createNoOpMock( ConfigurationLoader::class ),
			new \TitleFactory(),
			LoggerFactory::getInstance( 'test' )
		);
		$this->assertInstanceOf(
			Tracker::class,
			$factory->getTracker( new UserIdentityValue( 1, 'Foo', 0 ) )
		);
	}

}
