<?php

namespace GrowthExperiments\Tests;

use DateTime;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\UserImpact\UserImpact
 */
class ExpensiveUserImpactTest extends MediaWikiUnitTestCase {

	public function testGetters() {
		$dailyTotalViews = [ '2022-08-24' => 100, '2022-08-25' => 150 ];
		$dailyArticleViews = [
			'Foo' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
			'Bar' => [ '2022-08-24' => 30, '2022-08-25' => 40 ],
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			new UserTimeCorrection( 'System|0' ),
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			 new EditingStreak()
		);
		$this->assertInstanceOf( ExpensiveUserImpact::class, $userImpact );
		$this->assertSame( $dailyTotalViews, $userImpact->getDailyTotalViews() );
		$this->assertSame( $dailyArticleViews, $userImpact->getDailyArticleViews() );
	}

	public function testSerialization() {
		ConvertibleTimestamp::setFakeTime( time() );

		$dailyTotalViews = [ '2022-08-24' => 100, '2022-08-25' => 150 ];
		$dailyArticleViews = [
			'Foo' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
			'Bar' => [ '2022-08-24' => 30, '2022-08-25' => 40 ],
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak()
		);
		$data = $userImpact->jsonSerialize();
		$this->assertSame( $dailyTotalViews, $data['dailyTotalViews'] );
		$rehydrated = ExpensiveUserImpact::newFromJsonArray( $data );
		$this->assertEquals( $userImpact, $rehydrated );
	}

}
