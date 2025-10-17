<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\UserImpact\ComputeEditingStreaks;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\Json\JsonCodec;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\UserImpact\UserImpact
 */
class UserImpactTest extends MediaWikiUnitTestCase {

	public function testGetters(): void {
		$userImpact = new UserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			(int)wfTimestamp( TS_UNIX, '20200101000000' ),
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

	public function testSerialization(): void {
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
			(int)wfTimestamp( TS_UNIX, '20200101000000' ),
			new EditingStreak(
				ComputeEditingStreaks::makeDatePeriod(
					'2019-01-01',
					'2019-01-10',
				),
				12,
			),
			0,
			10
		);

		$data = $userImpact->jsonSerialize();
		$this->assertSame( 1, $data['userId'] );
		$this->assertSame( 80, $data['newcomerTaskEditCount'] );
		$rehydrated = UserImpact::newFromJsonArray( $data );
		$this->assertEquals( $userImpact, $rehydrated );

		$codec = new JsonCodec( null );
		$impactSerializedAsJsonCodex = $codec->toJsonString( [ $userImpact ] );
		$recreatedUserImpactArray = $codec->newFromJsonString( $impactSerializedAsJsonCodex );
		$this->assertEquals( [ $userImpact ], $recreatedUserImpactArray );
		$this->assertInstanceOf( UserImpact::class, $recreatedUserImpactArray[0] );
	}

}
