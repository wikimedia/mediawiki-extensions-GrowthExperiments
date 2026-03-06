<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Campaigns\CampaignLoader;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\Campaigns\CampaignLoader
 */
class CampaignLoaderTest extends MediaWikiUnitTestCase {

	public static function provideCampaignScenarios(): iterable {
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

	/**
	 * @dataProvider provideCampaignScenarios
	 */
	public function testGetCampaign(
		?string $urlParam,
		bool $isResourceLoader,
		bool $userSafeToLoad,
		string $expected
	): void {
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

		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );

		$campaignLoader = new CampaignLoader( $context, $userOptionsLookupMock );

		$result = $campaignLoader->getCampaign();
		$this->assertSame( $expected, $result );
	}
}
