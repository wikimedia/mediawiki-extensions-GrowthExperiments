<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModules\NewImpact;
use GrowthExperiments\VariantHooks;
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

	public static function provideGetImpactModule() {
		// configFlag, $requestData, $userVariant, $expectedModuleClass
		return [
			[ false, [ 'new-impact' => '1' ], VariantHooks::VARIANT_CONTROL, NewImpact::class ],
			[ true, [], VariantHooks::VARIANT_CONTROL, NewImpact::class ],
		];
	}

}
