<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * A minimal test case, just to make sure we get notified when interface changes break the class.
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader
 */
class ErrorForwardingConfigurationLoaderTest extends MediaWikiUnitTestCase {

	public function testLoadTaskTypes() {
		$status = StatusValue::newFatal( 'foo' );
		$loader = new ErrorForwardingConfigurationLoader( $status );
		$this->assertSame( $status, $loader->loadTaskTypes() );
	}

}
