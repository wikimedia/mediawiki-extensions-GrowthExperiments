<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\UserImpact\StaticUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\UserImpact\StaticUserImpactLookup
 */
class StaticUserImpactLookupTest extends MediaWikiUnitTestCase {

	public function testGetUserImpact() {
		$userImpacts = [
			1 => new UserImpact(
				UserIdentityValue::newRegistered( 1, 'User1' ),
				10,
				[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
				[ '2022-08-24' => 10, '2022-08-25' => 20 ],
				new UserTimeCorrection( 'System|0' ),
				80,
				wfTimestamp( TS_UNIX, '20200101000000' )
			),
			2 => new UserImpact(
				UserIdentityValue::newRegistered( 2, 'User2' ),
				20,
				[ NS_MAIN => 200, NS_TALK => 20, NS_USER_TALK => 30 ],
				[ '2022-08-24' => 10, '2022-08-25' => 20 ],
				new UserTimeCorrection( 'System|0' ),
				90,
				wfTimestamp( TS_UNIX, '20200909000000' )
			),
		];
		$lookup = new StaticUserImpactLookup( $userImpacts );
		$this->assertSame( $userImpacts[1], $lookup->getUserImpact( UserIdentityValue::newRegistered( 1, 'User1' ) ) );
		$this->assertSame( $userImpacts[2], $lookup->getUserImpact( UserIdentityValue::newRegistered( 2, 'User2' ) ) );
		$this->assertNull( $lookup->getUserImpact( UserIdentityValue::newRegistered( 3, 'User3' ) ) );
	}

}