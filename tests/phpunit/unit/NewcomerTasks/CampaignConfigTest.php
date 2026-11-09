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

	public function testConfigNotSet() {
		$campaignConfig = new CampaignConfig();
		$this->assertArrayEquals( $campaignConfig->getCampaignTopics(), [] );
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-glam-2022' ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-glam-2022' ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( null ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign(),
			[]
		);
	}

	public function testSingleCampaign() {
		$topicIds = [ 'argentina', 'mexico', 'chile' ];
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'topics' => $topicIds,
				'pattern' => '/^growth-glam-mexico-2022$/',
			],
		], [
			'argentina' => 'growtharticletopic:argentina',
			'mexico' => 'growtharticletopic:mexico',
			'chile' => 'growtharticletopic:chile',
		] );
		$this->assertEquals(
			'growth-glam-2022',
			$campaignConfig->getCampaignIndexFromCampaignTerm( 'growth-glam-mexico-2022' )
		);
		$this->assertArrayEquals( $campaignConfig->getCampaignTopics(), [
			[ 'id' => 'argentina', 'searchExpression' => 'growtharticletopic:argentina' ],
			[ 'id' => 'mexico', 'searchExpression' => 'growtharticletopic:mexico' ],
			[ 'id' => 'chile', 'searchExpression' => 'growtharticletopic:chile' ],
		] );
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-glam-2022' ),
			$topicIds
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-glam-2022' ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign(),
			$topicIds
		);
	}

	public function testMultipleCampaigns() {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'topics' => [ 'argentina', 'mexico', 'chile' ],
				'pattern' => '/^growth-glam-2022$/',
			],
			'growth-argentina-2022' => [
				'topics' => [ 'argentina', 'argentina-expanded' ],
				'pattern' => '/^growth-argentina-2022$/',
			],
		], [
			'argentina' => 'growtharticletopic:argentina',
			'mexico' => 'growtharticletopic:mexico',
			'chile' => 'growtharticletopic:chile',
			'argentina-expanded' => 'morelikethis:argentina',
		] );
		$this->assertArrayEquals(
			$campaignConfig->getCampaignTopics(),
			[
				[ 'id' => 'argentina', 'searchExpression' => 'growtharticletopic:argentina' ],
				[ 'id' => 'mexico', 'searchExpression' => 'growtharticletopic:mexico' ],
				[ 'id' => 'chile', 'searchExpression' => 'growtharticletopic:chile' ],
				[ 'id' => 'argentina-expanded', 'searchExpression' => 'morelikethis:argentina' ],
			]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-glam-2022' ),
			[ 'argentina', 'mexico', 'chile' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-argentina-2022' ),
			[ 'argentina', 'argentina-expanded' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-glam-2022' ),
			[ 'argentina-expanded' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-argentina-2022' ),
			[ 'mexico', 'chile' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign(),
			[ 'argentina', 'mexico', 'chile', 'argentina-expanded' ]
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

	public function testGetTopicsToExcludeForUser() {
		$mockUserOptionsLookup = $this->createNoOpMock( UserOptionsLookup::class, [ 'getOption' ] );
		$campaignConfig = new CampaignConfig( [
			'some-campaign' => [
				'topics' => [ 'topic-1' ],
				'pattern' => '/^growth-glam-2022$/',
			],
			'another-campaign' => [
				'topics' => [ 'topic-2' ],
				'pattern' => '/^growth-thankyou-2022$/',
			],
		], [
			'topic-1' => 'growtharticletopic:topic-1',
			'topic-2' => 'growtharticletopic:topic-2',
		], $mockUserOptionsLookup );

		$user = new UserIdentityValue( 1, 'User1' );
		$getOptionMock = $mockUserOptionsLookup->method( 'getOption' );

		// The user is in the campaign
		$getOptionMock->willReturn( 'growth-glam-2022' );
		// Should return topic-2 to exclude because the user is in the campaign with topic-1
		$this->assertArrayEquals(
			[ 'topic-2' ],
			$campaignConfig->getTopicsToExcludeForUser( $user )
		);

		// The user is in the campaign
		$getOptionMock->willReturn( 'growth-thankyou-2022' );
		// Should return topic-1 to exclude because the user is in the campaign with topic-2
		$this->assertArrayEquals(
			[ 'topic-1' ],
			$campaignConfig->getTopicsToExcludeForUser( $user )
		);

		// The user is NOT in any known campaign
		$getOptionMock->willReturn( 'not-in-any-informed-campaign' );
		// Should return topics to exclude because the user is NOT any of the campaigns
		$this->assertArrayEquals(
			[ 'topic-1', 'topic-2' ],
			$campaignConfig->getTopicsToExcludeForUser( $user )
		);

		// The user is NOT in any known campaign
		$getOptionMock->willReturn( null );
		// Should return topics to exclude because the user is NOT any of the campaigns
		$this->assertArrayEquals(
			[ 'topic-1', 'topic-2' ],
			$campaignConfig->getTopicsToExcludeForUser( $user )
		);
	}
}
