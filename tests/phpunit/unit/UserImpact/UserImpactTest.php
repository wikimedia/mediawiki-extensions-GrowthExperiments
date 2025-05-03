<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\UserImpact\UserImpact
 */
class UserImpactTest extends MediaWikiUnitTestCase {

	public function testGetters() {
		$userImpact = new UserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			new EditingStreak(),
			0,
			10
		);
		$this->assertInstanceOf( UserImpact::class, $userImpact );
		$this->assertSame( 10, $userImpact->getReceivedThanksCount() );
		$this->assertSame( 5, $userImpact->getGivenThanksCount() );
		$this->assertSame( [ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			$userImpact->getEditCountByNamespace() );
		$this->assertSame( 1, $userImpact->getRevertedEditCount() );
		$this->assertSame( 100, $userImpact->getEditCountIn( NS_MAIN ) );
		$this->assertSame( 80, $userImpact->getNewcomerTaskEditCount() );
		$this->assertSame( (int)wfTimestamp( TS_UNIX, '20200101000000' ), $userImpact->getLastEditTimestamp() );
		$this->assertSame( 10, $userImpact->getTotalEditsCount() );
	}

	public function testSerialization() {
		ConvertibleTimestamp::setFakeTime( time() );

		$userImpact = new UserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			new EditingStreak(),
			0,
			10
		);

		$data = $userImpact->jsonSerialize();
		$this->assertSame( 1, $data['userId'] );
		$this->assertSame( 80, $data['newcomerTaskEditCount'] );
		$rehydrated = UserImpact::newFromJsonArray( $data );
		$this->assertEquals( $userImpact, $rehydrated );
	}

}
