<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {

	protected const DEPRECATED_SERVICES = [];

	protected const IGNORED_SERVICES = [];

	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		if ( in_array( $name, self::DEPRECATED_SERVICES, true ) ) {
			$this->hideDeprecated( "$name service" );
		}
		MediaWikiServices::getInstance()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public function provideService() {
		$wiring = require __DIR__ . '/../../../ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			if ( in_array( $name, self::IGNORED_SERVICES, true ) ) {
				continue;
			}
			yield $name => [ $name ];
		}
	}

	public function testErrorForwardingTaskSuggester() {
		$this->overrideConfigValues( [
			'SearchType' => 'Foo',
		] );
		$this->resetServices();
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$taskSuggesterFactory = $growthServices->getTaskSuggesterFactory();
		$this->assertInstanceOf( StaticTaskSuggesterFactory::class, $taskSuggesterFactory );
		$taskSuggester = $taskSuggesterFactory->create();
		$this->assertInstanceOf( ErrorForwardingTaskSuggester::class, $taskSuggester );
	}

	public function testLocalSearchTaskSuggester() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$this->overrideConfigValues( [
			'SearchType' => 'CirrusSearch',
		] );
		$this->resetServices();
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$taskSuggesterFactory = $growthServices->getTaskSuggesterFactory();
		$this->assertInstanceOf( DecoratingTaskSuggesterFactory::class, $taskSuggesterFactory );
		$taskSuggester = $taskSuggesterFactory->create();
		$this->assertInstanceOf( QualityGateDecorator::class, $taskSuggester );
	}

	public function testRemoteSearchTaskSuggester() {
		$this->overrideConfigValue( 'GENewcomerTasksRemoteApiUrl', 'https://en.wikipedia.org/w/api.php' );
		$this->resetServices();
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'loadTopics' )->willReturn( [] );
		$configurationLoader->method( 'loadTaskTypes' )->willReturn( [] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$taskSuggesterFactory = $growthServices->getTaskSuggesterFactory();
		$this->assertInstanceOf( RemoteSearchTaskSuggesterFactory::class, $taskSuggesterFactory );
		$taskSuggester = $taskSuggesterFactory->create();
		$this->assertInstanceOf( RemoteSearchTaskSuggester::class, $taskSuggester );
	}

}
