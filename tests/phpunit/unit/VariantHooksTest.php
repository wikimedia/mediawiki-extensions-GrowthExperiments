<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
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
				'growthexperiments-homepage-variant' => [ 'type' => 'api' ],
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
			VariantHooks::VARIANTS,
			'control',
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
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'MetricsPlatform', '*' )
			->willReturn( false );
		return new VariantHooks(
			$this->createNoOpMock( UserOptionsManager::class ),
			$this->createNoOpMock( CampaignConfig::class ),
			$this->createNoOpMock( SpecialPageFactory::class ),
			new HashConfig( [
				'GEHomepageDefaultVariant' => 'control',
			] ),
			$extensionRegistry,
			$this->createNoOpMock( AbstractExperimentManager::class ),
		);
	}

	/**
	 * @covers \GrowthExperiments\VariantHooks::getCampaign
	 * @dataProvider provideCampaignScenarios
	 */
	public function testGetCampaign(
		?string $urlParam, bool $isResourceLoader, bool $userSafeToLoad, string $expected ) {
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getVal' )
			->with( 'campaign', '' )
			->willReturn( $urlParam ?? '' );

		$user = $this->createMock( User::class );
		$user->method( 'isSafeToLoad' )
			->willReturn( $userSafeToLoad );

		$context = new RequestContext();
		$context->setRequest( $request );
		$context->setUser( $user );

		$result = VariantHooks::getCampaign( $context );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for testGetCampaign
	 * @return array[]
	 */
	public static function provideCampaignScenarios(): array {
		return [
			'URL parameter exists' => [
				'winter2024',
				false,
				true,
				'winter2024',
			],
			'Empty URL param' => [
				'',
				false,
				false,
				'',
			],
		];
	}

}
