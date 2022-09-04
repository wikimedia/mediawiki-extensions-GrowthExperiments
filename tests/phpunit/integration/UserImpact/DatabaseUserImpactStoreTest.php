<?php

namespace GrowthExperiments\Tests;

use DateTime;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\UserTimeCorrection;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\UserImpact\DatabaseUserImpactStore
 */
class DatabaseUserImpactStoreTest extends MediaWikiIntegrationTestCase {

	/** @inheritDoc */
	protected $tablesUsed = [ DatabaseUserImpactStore::TABLE_NAME ];

	public function testGetSetUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );
		$store = new DatabaseUserImpactStore( $this->db, $this->db );
		$user = $this->getTestUser()->getUserIdentity();
		$user2 = $this->getMutableTestUser()->getUserIdentity();
		$user3 = $this->getMutableTestUser()->getUserIdentity();

		$userImpact = new UserImpact(
			$user,
			100,
			[ NS_MAIN => 10, NS_USER_TALK => 10 ],
			[ '2020-01-01' => 10, '2020-01-02' => 20 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			10,
			wfTimestamp( TS_UNIX, '20200101000000' )
		);
		$store->setUserImpact( $userImpact );
		$this->assertEquals( $userImpact, $store->getUserImpact( $user ) );
		$this->assertNull( $store->getExpensiveUserImpact( $user ) );

		$userImpact2 = new UserImpact(
			$user2,
			200,
			[ NS_MAIN => 20, NS_USER_TALK => 20 ],
			[ '2020-01-01' => 20, '2020-01-02' => 30 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			20,
			wfTimestamp( TS_UNIX, '20200102000000' )
		);
		$store->setUserImpact( $userImpact2 );
		$this->assertEquals( $userImpact2, $store->getUserImpact( $user2 ) );
		$this->assertNotEquals( $userImpact2, $store->getUserImpact( $user ) );

		$this->assertNull( $store->getUserImpact( $user3 ) );
	}

	public function testGetSetExpensiveUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );
		$store = new DatabaseUserImpactStore( $this->db, $this->db );
		$user = $this->getTestUser()->getUserIdentity();

		$expensiveUserImpact = new ExpensiveUserImpact(
			$user,
			300,
			[ NS_MAIN => 30, NS_USER_TALK => 30 ],
			[ '2020-01-01' => 30, '2020-01-02' => 40 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			30,
			wfTimestamp( TS_UNIX, '20200103000000' ),
			[ '2020-01-01' => 1000, '2020-01-02' => 2000 ],
			[ '2020-01-01' => [ 'Foo' => 500, 'Bar' => 500 ], '2020-01-02' => [ 'Foo' => 1000, 'Bar' => 1000 ] ]
		);
		$store->setUserImpact( $expensiveUserImpact );
		$this->assertEquals( $expensiveUserImpact, $store->getUserImpact( $user ) );
		$this->assertEquals( $expensiveUserImpact, $store->getExpensiveUserImpact( $user ) );
	}

	public function testUpdateUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );
		$store = new DatabaseUserImpactStore( $this->db, $this->db );
		$user = $this->getTestUser()->getUserIdentity();

		$userImpact = new UserImpact(
			$user,
			100,
			[ NS_MAIN => 10, NS_USER_TALK => 10 ],
			[ '2020-01-01' => 10, '2020-01-02' => 20 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			10,
			wfTimestamp( TS_UNIX, '20200101000000' )
		);
		$updatedUserImpact = new UserImpact(
			$user,
			150,
			[ NS_MAIN => 15, NS_USER_TALK => 15 ],
			[ '2020-01-01' => 15, '2020-01-02' => 25 ],
			new UserTimeCorrection( 'System|0', new DateTime( '@' . ConvertibleTimestamp::time() ) ),
			15,
			wfTimestamp( TS_UNIX, '20200101120000' )
		);

		$store->setUserImpact( $userImpact );
		$this->assertEquals( $userImpact, $store->getUserImpact( $user ) );
		$store->setUserImpact( $updatedUserImpact );
		$this->assertEquals( $updatedUserImpact, $store->getUserImpact( $user ) );
	}

}
