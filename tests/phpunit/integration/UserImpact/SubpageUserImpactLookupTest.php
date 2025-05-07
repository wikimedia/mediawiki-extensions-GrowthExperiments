<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\UserImpact\ComputeEditingStreaks;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\StaticUserImpactLookup;
use GrowthExperiments\UserImpact\SubpageUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\Content\JsonContent;
use MediaWiki\User\UserIdentityValue;
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
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 20 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			new EditingStreak(
				ComputeEditingStreaks::makeDatePeriod( '2022-08-24', '2022-08-25' ),
				30
			),
			0,
			125
		);
		$fallbackUserImpact1 = new UserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			20,
			10,
			[ NS_MAIN => 200, NS_TALK => 20, NS_USER_TALK => 30 ],
			[ '2022-08-24' => 11, '2022-08-25' => 21 ],
			[ 'copyedit' => 11, 'link-recommendation' => 21 ],
			2,
			100,
			wfTimestamp( TS_UNIX, '20200909000000' ),
			new EditingStreak(),
			0,
			null
		);
		$userImpact2 = new UserImpact(
			UserIdentityValue::newRegistered( 2, 'User2' ),
			30,
			15,
			[ NS_MAIN => 300, NS_TALK => 30, NS_USER_TALK => 40 ],
			[ '2022-08-24' => 12, '2022-08-25' => 22 ],
			[ 'copyedit' => 12, 'link-recommendation' => 22 ],
			3,
			110,
			wfTimestamp( TS_UNIX, '20220101000000' ),
			new EditingStreak(),
			0,
			null
		);
		$this->makeJsonPage( 'User:User1/userimpact.json', [
			'@version' => UserImpact::VERSION,
			'userId' => 1,
			'userName' => 'User1',
			'receivedThanksCount' => 10,
			'givenThanksCount' => 5,
			'editCountByNamespace' => [ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			'editCountByDay' => [ '2022-08-24' => 10, '2022-08-25' => 20 ],
			'editCountByTaskType' => [ 'copyedit' => 10, 'link-recommendation' => 20 ],
			'revertedEditCount' => 1,
			'timeZone' => [ 'System|0', 0 ],
			'newcomerTaskEditCount' => 80,
			'lastEditTimestamp' => (int)wfTimestamp( TS_UNIX, '20200101000000' ),
			'generatedAt' => ConvertibleTimestamp::time(),
			'longestEditingStreak' => [
				'datePeriod' => [
					'start' => '2022-08-24',
					'end' => '2022-08-25',
					'days' => 2
				],
				'totalEditCountForPeriod' => 30
			],
			'totalArticlesCreatedCount' => 0,
			'totalUserEditCount' => 125,
		] );

		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		$lookup = new SubpageUserImpactLookup( $wikiPageFactory );
		$this->assertEquals(
			$userImpact1,
			$lookup->getUserImpact( UserIdentityValue::newRegistered( 1, 'User1' ) )
		);
		$this->assertNull( $lookup->getExpensiveUserImpact( UserIdentityValue::newRegistered( 1, 'User1' ) ) );
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
