<?php

namespace GrowthExperiments\Tests;

use FauxRequest;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\NewImpact;
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
	 */
	public function testGetImpactModule() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$context = RequestContext::getMain();
		$this->assertInstanceOf( Impact::class, $moduleRegistry->get( 'impact', $context ) );
	}

	/**
	 * @covers ::get
	 * @covers ::getWiring
	 */
	public function testGetNewImpactModuleWithConfigOverride() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$this->overrideConfigValue( 'GEUseNewImpactModule', true );
		$context = RequestContext::getMain();
		$this->assertInstanceOf( NewImpact::class, $moduleRegistry->get( 'impact', $context ) );
	}

	/**
	 * @covers ::get
	 * @covers ::getWiring
	 */
	public function testGetNewImpactModuleWithQueryParameter() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$context = RequestContext::getMain();
		$this->setRequest( new FauxRequest( [ 'new-impact' => 1 ] ) );
		$this->assertInstanceOf( NewImpact::class, $moduleRegistry->get( 'impact', $context ) );
	}

}
