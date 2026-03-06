<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\StaticExperimentManager;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\VariantHooks
 */
class VariantHooksTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( VariantHooks::class, $this->getVariantHooksMock() );
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferences() {
		$variantHooks = $this->getVariantHooksMock();
		$prefs = [];
		$variantHooks->onGetPreferences( $this->createNoOpMock( User::class ), $prefs );
		$this->assertArrayEquals(
			[
				'growthexperiments-campaign' => [ 'type' => 'api' ],
			],
			$prefs
		);
	}

	/**
	 * @covers ::onResourceLoaderExcludeUserOptions
	 */
	public function testOnResourceLoaderExcludeUserOptions() {
		$keysToExclude = [];
		$variantHooks = $this->getVariantHooksMock();
		$variantHooks->onResourceLoaderExcludeUserOptions(
			$keysToExclude,
			$this->createNoOpMock( RL\Context::class )
		);
		$this->assertArrayEquals( [ 'growthexperiments-campaign' ], $keysToExclude );
	}

	/**
	 * @covers ::onResourceLoaderGetConfigVars
	 */
	public function testOnResourceLoaderGetConfigVars() {
		$vars = [];
		$this->getVariantHooksMock()->onResourceLoaderGetConfigVars(
			$vars,
			$this->createNoOpMock( Skin::class ),
			new HashConfig( [ 'GEHomepageDefaultVariant' => 'control' ] )
		);
		$this->assertArrayEquals( [
			'wgGEDefaultUserVariant' => 'control',
		], $vars );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreated() {
		$user = $this->createNoOpMock( User::class );
		// If autocreated was false, we would hit code requiring an integration test.
		$this->getVariantHooksMock()->onLocalUserCreated( $user, true );
		$this->addToAssertionCount( 1 );
	}

	private function getVariantHooksMock(): VariantHooks {
		$featureManager = $this->createMock( FeatureManager::class );
		$featureManager->method( 'useTestKitchen' )->willReturn( false );
		return new VariantHooks(
			$this->createNoOpMock( UserOptionsManager::class ),
			$this->createNoOpMock( CampaignConfig::class ),
			$this->createNoOpMock( SpecialPageFactory::class ),
			new StaticExperimentManager( new ServiceOptions(
				StaticExperimentManager::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'GEHomepageDefaultVariant' => 'control',
				] ),
			) ),
			$this->createNoOpMock( CampaignLoader::class ),
		);
	}

}
