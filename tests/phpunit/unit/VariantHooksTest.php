<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\VariantHooks;
use HashConfig;
use MediaWiki\User\UserOptionsManager;
use MediaWikiUnitTestCase;
use RequestContext;
use ResourceLoaderContext;
use Skin;
use Title;
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
			$this->createNoOpMock( ResourceLoaderContext::class )
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
	 * @covers ::isGrowthDonorCampaign
	 * @dataProvider isGrowthDonorCampaignDataProvider
	 */
	public function testIsGrowthDonorCampaign(
		string $campaignPattern,
		string $campaignFromRequestVal,
		bool $isSpecial,
		bool $expected
	) {
		$variantHooks = $this->getVariantHooksMock();
		$contextMock = $this->createMock( RequestContext::class );
		$contextMock->method( 'getConfig' )->willReturn(
			new HashConfig( [ 'GECampaignPattern' => $campaignPattern ] )
		);
		$requestContext = new \FauxRequest( [ 'campaign' => $campaignFromRequestVal ] );
		$contextMock->method( 'getRequest' )->willReturn( $requestContext );
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )->with( 'CreateAccount' )
			->willReturn( $isSpecial );
		$contextMock->method( 'getTitle' )->willReturn( $title );
		$result = $variantHooks::isGrowthDonorCampaign( $contextMock );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for testIsGrowthDonorCampaign
	 */
	public function isGrowthDonorCampaignDataProvider(): array {
		return [
			[
				'',
				'',
				false,
				false
			],
			[
				'/^foo$/',
				'foo',
				true,
				true
			],
			[
				'/^foo$/',
				'bar',
				true,
				false
			]
		];
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
			$this->createNoOpMock( UserOptionsManager::class )
		);
	}

}
