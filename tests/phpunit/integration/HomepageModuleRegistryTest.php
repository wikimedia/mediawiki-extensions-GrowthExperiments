<?php

namespace GrowthExperiments\Tests;

use DerivativeRequest;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\NewImpact;
use GrowthExperiments\VariantHooks;
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
		$this->assertInstanceOf( IDashboardModule::class, $moduleRegistry->get( $moduleId, $context ) );
	}

	public function provideGet() {
		foreach ( HomepageModuleRegistry::getModuleIds() as $moduleId ) {
			yield [ $moduleId ];
		}
	}

	/**
	 * @covers ::get
	 * @covers ::getWiring
	 * @dataProvider provideGetImpactModule
	 */
	public function testGetImpactModule(
		bool $configFlag,
		array $requestData,
		string $userVariant,
		string $expectedModuleClass
	) {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$context = RequestContext::getMain();

		$this->overrideConfigValue( 'GEUseNewImpactModule', $configFlag );
		$context->setRequest( new DerivativeRequest( $context->getRequest(), $requestData ) );
		$user = $this->getMutableTestUser()->getUser();
		$growthServices->getExperimentUserManager()->setVariant( $user, $userVariant );
		$context->setUser( $user );

		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$this->assertInstanceOf( $expectedModuleClass, $moduleRegistry->get( 'impact', $context ) );
	}

	public function provideGetImpactModule() {
		// configFlag, $requestData, $userVariant, $expectedModuleClass
		return [
			[ false, [], VariantHooks::VARIANT_CONTROL, Impact::class ],
			[ false, [ 'new-impact' => '1' ], VariantHooks::VARIANT_CONTROL, NewImpact::class ],
			[ true, [], VariantHooks::VARIANT_CONTROL, NewImpact::class ],
			[ true, [], VariantHooks::VARIANT_OLDIMPACT, Impact::class ],
			[ true, [ 'new-impact' => '1' ], VariantHooks::VARIANT_OLDIMPACT, NewImpact::class ],
			[ true, [ 'new-impact' => '0' ], VariantHooks::VARIANT_CONTROL, Impact::class ],
		];
	}

}
