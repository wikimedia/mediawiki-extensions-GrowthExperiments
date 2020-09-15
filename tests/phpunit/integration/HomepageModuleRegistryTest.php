<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModule;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @coversDefaultClass \GrowthExperiments\Homepage\HomepageModuleRegistry
 */
class HomepageModuleRegistryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGet
	 * @covers ::get
	 */
	public function testGet( $moduleId ) {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$context = RequestContext::getMain();
		$this->assertInstanceOf( HomepageModule::class, $moduleRegistry->get( $moduleId, $context ) );
	}

	public function provideGet() {
		foreach ( HomepageModuleRegistry::getModuleIds() as $moduleId ) {
			yield [ $moduleId ];
		}
	}

}
