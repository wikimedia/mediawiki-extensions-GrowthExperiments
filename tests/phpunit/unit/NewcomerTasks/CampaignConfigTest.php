<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\VariantHooks;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\CampaignConfig
 */
class CampaignConfigTest extends MediaWikiUnitTestCase {

	public function testSingleCampaign() {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'pattern' => '/^growth-glam-mexico-2022$/',
			],
		] );
		$this->assertEquals(
			'growth-glam-2022',
			$campaignConfig->getCampaignIndexFromCampaignTerm( 'growth-glam-mexico-2022' )
		);
	}

	public function testShouldSkipWelcomeSurvey() {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'pattern' => '/^growth-glam-mexico-2022$/',
			],
		] );
		$this->assertFalse(
			$campaignConfig->shouldSkipWelcomeSurvey( 'growth-glam-mexico-2022' )
		);
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'pattern' => '/^growth-glam-mexico-2022$/',
				'skipWelcomeSurvey' => true,
			],
		] );
		$this->assertTrue(
			$campaignConfig->shouldSkipWelcomeSurvey( 'growth-glam-mexico-2022' )
		);
	}

	public function testShouldSkipWelcomeSurveyWithoutMatchingCampaign(): void {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'pattern' => '/^growth-glam-mexico-2022$/',
				'skipWelcomeSurvey' => true,
			],
		] );
		$this->assertFalse( $campaignConfig->shouldSkipWelcomeSurvey( '' ) );
		$this->assertFalse( $campaignConfig->shouldSkipWelcomeSurvey( 'some-other-campaign' ) );
	}

	/**
	 * @dataProvider provideIsGrowthCampaign
	 */
	public function testIsGrowthCampaign( string $campaignParameter, bool $expected ): void {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'pattern' => '/^growth-glam-mexico-2022$/',
			],
		] );
		$this->assertSame( $expected, $campaignConfig->isGrowthCampaign( $campaignParameter ) );
	}

	public static function provideIsGrowthCampaign(): iterable {
		yield 'empty campaign parameter' => [ '', false ];
		yield 'matching campaign parameter' => [ 'growth-glam-mexico-2022', true ];
		yield 'campaign parameter without a match' => [ 'some-other-campaign', false ];
	}

	/**
	 * @dataProvider provideShouldSkipImageRecommendationDailyTaskLimit
	 */
	public function testShouldSkipImageRecommendationDailyTaskLimit(
		array $campaignSettings,
		bool $expected
	): void {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [ 'pattern' => '/^growth-glam-mexico-2022$/' ] + $campaignSettings,
		] );
		$this->assertSame(
			$expected,
			$campaignConfig->shouldSkipImageRecommendationDailyTaskLimit( 'growth-glam-2022' )
		);
	}

	public static function provideShouldSkipImageRecommendationDailyTaskLimit(): iterable {
		yield 'no quality gates to skip' => [ [], false ];
		yield 'daily limit skipped for image recommendation' => [
			[ 'qualityGateIdsToSkip' => [ 'image-recommendation' => [ 'dailyLimit' ] ] ],
			true,
		];
		yield 'no gates skipped for image recommendation' => [
			[ 'qualityGateIdsToSkip' => [ 'image-recommendation' => [] ] ],
			false,
		];
		yield 'daily limit skipped for another task type' => [
			[ 'qualityGateIdsToSkip' => [ 'link-recommendation' => [ 'dailyLimit' ] ] ],
			false,
		];
	}

	/**
	 * @dataProvider provideShouldSkipImageRecommendationDailyTaskLimitForUser
	 */
	public function testShouldSkipImageRecommendationDailyTaskLimitForUser(
		?string $userCampaign,
		bool $expected
	): void {
		$user = new UserIdentityValue( 1, 'User1' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->with( $user, VariantHooks::GROWTH_CAMPAIGN )
			->willReturn( $userCampaign );
		$campaignConfig = new CampaignConfig(
			config: [
				'growth-glam-2022' => [
					'pattern' => '/^growth-glam-mexico-2022$/',
					'qualityGateIdsToSkip' => [ 'image-recommendation' => [ 'dailyLimit' ] ],
				],
				'growth-thankyou-2022' => [
					'pattern' => '/^growth-thankyou-2022$/',
				],
			],
			userOptionsLookup: $userOptionsLookup
		);
		$this->assertSame(
			$expected,
			$campaignConfig->shouldSkipImageRecommendationDailyTaskLimitForUser( $user )
		);
	}

	public static function provideShouldSkipImageRecommendationDailyTaskLimitForUser(): iterable {
		yield 'user without a campaign' => [ null, false ];
		yield 'user in a campaign without a match' => [ 'some-other-campaign', false ];
		yield 'user in a campaign that skips the daily limit' => [ 'growth-glam-mexico-2022', true ];
		yield 'user in a campaign that keeps the daily limit' => [ 'growth-thankyou-2022', false ];
	}

	public function testGetMessageKey() {
		$campaignConfig = new CampaignConfig( [] );
		$this->assertEquals(
			'signupcampaign',
			$campaignConfig->getMessageKey( 'some-signup-campaign' )
		);
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'pattern' => '/^growth-glam-mexico-2022$/',
				'messageKey' => 'growthglamcampaignkey',
			],
		] );
		$this->assertEquals(
			'growthglamcampaignkey',
			$campaignConfig->getMessageKey( 'growth-glam-mexico-2022' )
		);
	}
}
