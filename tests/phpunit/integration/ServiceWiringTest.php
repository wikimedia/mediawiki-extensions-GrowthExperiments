<?php

namespace GrowthExperiments\Tests;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		MediaWikiServices::getInstance()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public function provideService() {
		$wiring = require __DIR__ . '/../../../ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}

}
