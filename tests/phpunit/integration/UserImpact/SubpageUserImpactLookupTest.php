<?php

namespace GrowthExperiments\Tests;

use DateTime;
use GrowthExperiments\UserImpact\StaticUserImpactLookup;
use GrowthExperiments\UserImpact\SubpageUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpact;
use JsonContent;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\UserImpact\SubpageUserImpactLookup
 */
class SubpageUserImpactLookupTest extends MediaWikiIntegrationTestCase {

	public function testGetUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );

		$userImpact1 = new UserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			80,
			wfTimestamp( TS_UNIX, '20200101000000' )
		);
		$fallbackUserImpact1 = new UserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			20,
			[ NS_MAIN => 200, NS_TALK => 20, NS_USER_TALK => 30 ],
			[ '2022-08-24' => 11, '2022-08-25' => 21 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			100,
			wfTimestamp( TS_UNIX, '20200909000000' )
		);
		$userImpact2 = new UserImpact(
			UserIdentityValue::newRegistered( 2, 'User2' ),
			30,
			[ NS_MAIN => 300, NS_TALK => 30, NS_USER_TALK => 40 ],
			[ '2022-08-24' => 12, '2022-08-25' => 22 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			110,
			wfTimestamp( TS_UNIX, '20220101000000' )
		);
		$this->makeJsonPage( 'User:User1/userimpact.json', [
			'userId' => 1,
			'userName' => 'User1',
			'receivedThanksCount' => 10,
			'editCountByNamespace' => [ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			'editCountByDay' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
			'timeZone' => [ 'System|0', 0 ],
			'newcomerTaskEditCount' => 80,
			'lastEditTimestamp' => (int)wfTimestamp( TS_UNIX, '20200101000000' ),
		] );

		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		$lookup = new SubpageUserImpactLookup( $wikiPageFactory );
		$this->assertEquals(
			$userImpact1,
			$lookup->getUserImpact( UserIdentityValue::newRegistered( 1, 'User1' ) )
		);
		$this->assertNull( $lookup->getUserImpact( UserIdentityValue::newRegistered( 2, 'User2' ) ) );

		$lookup = new SubpageUserImpactLookup( $wikiPageFactory, new StaticUserImpactLookup( [
			1 => $fallbackUserImpact1,
			2 => $userImpact2,
		] ) );
		 $this->assertEquals(
			$userImpact1,
			$lookup->getUserImpact( UserIdentityValue::newRegistered( 1, 'User1' ) )
		);
		$this->assertEquals(
			$userImpact2,
			$lookup->getUserImpact( UserIdentityValue::newRegistered( 2, 'User2' ) )
		);
		$this->assertNull( $lookup->getUserImpact( UserIdentityValue::newRegistered( 3, 'User3' ) ) );
	}

	private function makeJsonPage( string $title, array $data ) {
		$this->editPage( $title, new JsonContent( json_encode( $data, JSON_PRETTY_PRINT ) ) );
	}

}
