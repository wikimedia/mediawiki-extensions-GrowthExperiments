<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Homepage\HomepageModuleRegistry
 */
class HomepageModuleRegistryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGet
	 * @covers ::get
	 */
	public function testGet( $moduleId ) {
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$context = RequestContext::getMain();
		$this->assertInstanceOf( IDashboardModule::class, $moduleRegistry->get( $moduleId, $context ) );
	}

	public static function provideGet() {
		foreach ( HomepageModuleRegistry::getModuleIds() as $moduleId ) {
			yield [ $moduleId ];
		}
	}

}
