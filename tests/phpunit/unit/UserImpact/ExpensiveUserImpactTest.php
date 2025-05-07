<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\UserImpact\UserImpact
 */
class ExpensiveUserImpactTest extends MediaWikiUnitTestCase {

	public function testGetters() {
		$dailyTotalViews = [ '2022-08-24' => 100, '2022-08-25' => 150 ];
		$dailyArticleViews = [
			'Foo' => [
				'firstEditDate' => '2022-08-24',
				'newestEdit' => '20220825100000',
				'views' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
				'imageUrl' => null,
			],
			'Bar' => [
				'firstEditDate' => '2022-08-24',
				'newestEdit' => '20220825110000',
				'views' => [ '2022-08-24' => 30, '2022-08-25' => 40 ],
				'imageUrl' => null,
			],
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			 new EditingStreak(),
			0,
			null
		);
		$this->assertInstanceOf( ExpensiveUserImpact::class, $userImpact );
		$this->assertSame( $dailyTotalViews, $userImpact->getDailyTotalViews() );
		$this->assertSame( $dailyArticleViews, $userImpact->getDailyArticleViews() );
	}

	public function testSerialization() {
		ConvertibleTimestamp::setFakeTime( time() );

		$dailyTotalViews = [ '2022-08-24' => 100, '2022-08-25' => 150, '2022-08-26' => 0 ];
		$dailyArticleViews = [
			'Foo' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
			'Bar' => [ '2022-08-24' => 30, '2022-08-25' => 40 ],
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak(),
			0,
			null
		);
		$data = $userImpact->jsonSerialize();
		$this->assertSame(
			[ '2022-08-24' => 100, '2022-08-25' => 150 ],
			$data['dailyTotalViews']
		);
		$rehydrated = ExpensiveUserImpact::newFromJsonArray( $data );
		TestingAccessWrapper::newFromObject( $userImpact )->dailyTotalViews = [
			'2022-08-24' => 100, '2022-08-25' => 150
		];
		$this->assertEquals( $userImpact, $rehydrated );
	}

	/**
	 * @dataProvider provideIsPageViewDataStale
	 * @param bool $expectedStale
	 * @param array $dailyTotalViews
	 */
	public function testIsPageViewDataStale( bool $expectedStale, array $dailyTotalViews ) {
		ConvertibleTimestamp::setFakeTime( '2022-08-24T00:00:00Z' );
		$dailyArticleViews = [
			'Foo' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
			'Bar' => [ '2022-08-24' => 30, '2022-08-25' => 40 ],
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak(),
			0,
			null
		);

		$this->assertSame( $expectedStale, $userImpact->isPageViewDataStale() );
	}

	public static function provideIsPageViewDataStale() {
		return [
			'fresh' => [ false, [ '2022-08-24' => 100, '2022-08-25' => 150 ] ],
			'stale' => [ false, [ '2022-07-24' => 100, '2022-08-25' => 150 ] ],
			'no data' => [ true, [] ],
		];
	}

}
