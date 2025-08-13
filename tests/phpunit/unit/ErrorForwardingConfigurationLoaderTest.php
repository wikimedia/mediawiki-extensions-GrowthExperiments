<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;

/**
 * A minimal test case, just to make sure we get notified when interface changes break the class.
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader
 */
class ErrorForwardingConfigurationLoaderTest extends MediaWikiUnitTestCase {

	public function testLoadTaskTypes() {
		$status = StatusValue::newFatal( 'foo' );
		$loader = new ErrorForwardingConfigurationLoader( $status, new NullLogger() );
		$result = $loader->loadTaskTypes();
		$this->assertSame( 'foo', $result->getMessages()[0]->getKey() );
	}

	public function testGetTaskTypes() {
		$status = StatusValue::newFatal( 'foo' );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with(
				'Unexpected call to ConfigurationLoader::getTaskTypes when feature is disabled.' .
				' Called from: {class}::{function}.'
			);
		$loader = new ErrorForwardingConfigurationLoader( $status, $logger );
		$this->assertSame( [], $loader->getTaskTypes() );
	}

	public function testGetDisabledTaskTypes() {
		$status = StatusValue::newFatal( 'foo' );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with(
				'Unexpected call to ConfigurationLoader::getDisabledTaskTypes when feature is disabled.' .
				' Called from: {class}::{function}.'
			);
		$loader = new ErrorForwardingConfigurationLoader( $status, $logger );
		$this->assertSame( [], $loader->getDisabledTaskTypes() );
	}

}
