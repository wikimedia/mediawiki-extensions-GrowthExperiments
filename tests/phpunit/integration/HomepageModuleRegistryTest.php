<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModules\CommunityUpdates;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\NewImpact;
use GrowthExperiments\VariantHooks;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
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
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$context = RequestContext::getMain();
		$this->assertInstanceOf( IDashboardModule::class, $moduleRegistry->get( $moduleId, $context ) );
	}

	public static function provideGet() {
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

		$this->overrideConfigValue( 'GEUseNewImpactModule', $configFlag );
		$context = RequestContext::newExtraneousContext(
			Title::makeTitle( NS_SPECIAL, 'Blankpage' ),
			$requestData
		);
		$user = $this->getTestUser()->getUser();

		$growthServices->getExperimentUserManager()->setVariant( $user, $userVariant );
		$context->setUser( $user );

		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$this->assertInstanceOf( $expectedModuleClass, $moduleRegistry->get( 'impact', $context ) );
	}

	public static function provideGetImpactModule() {
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

	/**
	 * @covers ::get
	 * @covers ::getWiring
	 * @dataProvider provideGetCommunityUpdatesModule
	 */
	public function testGetCommunityUpdatesModule() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$context = RequestContext::getMain();

		$moduleRegistry = $growthServices->getHomepageModuleRegistry();
		$this->assertInstanceOf(
			CommunityUpdates::class, $moduleRegistry->get( 'community-updates', $context ) );
	}

	public static function provideGetCommunityUpdatesModule() {
		return [
			[ 'community-updates' ]
		];
	}

}
