<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\VariantHooks;
use HashConfig;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\User\UserOptionsManager;
use MediaWikiUnitTestCase;
use Skin;
use User;

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
				'growthexperiments-homepage-variant' => [ 'type' => 'api' ],
				'growthexperiments-campaign' => [ 'type' => 'api' ]
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
			VariantHooks::VARIANTS,
			'control'
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
		return new VariantHooks(
			$this->createNoOpMock( UserOptionsManager::class ),
			$this->createNoOpMock( CampaignConfig::class )
		);
	}

}
