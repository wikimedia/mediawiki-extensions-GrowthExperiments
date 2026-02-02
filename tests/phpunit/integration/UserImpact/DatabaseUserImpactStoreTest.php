<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\UserImpact\DatabaseUserImpactStore
 */
class DatabaseUserImpactStoreTest extends MediaWikiIntegrationTestCase {

	public function testGetSetUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );
		$store = new DatabaseUserImpactStore( $this->getServiceContainer()->getDBLoadBalancer(), new NullLogger() );
		$user = $this->getTestUser()->getUserIdentity();
		$user2 = $this->getMutableTestUser()->getUserIdentity();
		$user3 = $this->getMutableTestUser()->getUserIdentity();

		$userImpact = new UserImpact(
			$user,
			100,
			50,
			[ NS_MAIN => 10, NS_USER_TALK => 10 ],
			[ '2020-01-01' => 10, '2020-01-02' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 20 ],
			1,
			10,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			new EditingStreak(),
			0,
			null
		);
		$store->setUserImpact( $userImpact );
		$this->assertEquals( $userImpact, $store->getUserImpact( $user ) );
		$this->assertNull( $store->getExpensiveUserImpact( $user ) );

		$userImpact2 = new UserImpact(
			$user2,
			200,
			100,
			[ NS_MAIN => 20, NS_USER_TALK => 20 ],
			[ '2020-01-01' => 20, '2020-01-02' => 30 ],
			[ 'copyedit' => 20, 'link-recommendation' => 30 ],
			2,
			20,
			wfTimestamp( TS_UNIX, '20200102000000' ),
			new EditingStreak(),
			0,
			null
		);
		$store->setUserImpact( $userImpact2 );
		$this->assertEquals( $userImpact2, $store->getUserImpact( $user2 ) );
		$this->assertNotEquals( $userImpact2, $store->getUserImpact( $user ) );

		$this->assertNull( $store->getUserImpact( $user3 ) );
	}

	public function testGetSetExpensiveUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );
		$store = new DatabaseUserImpactStore( $this->getServiceContainer()->getDBLoadBalancer(), new NullLogger() );
		$user = $this->getTestUser()->getUserIdentity();

		$expensiveUserImpact = new ExpensiveUserImpact(
			$user,
			300,
			150,
			[ NS_MAIN => 30, NS_USER_TALK => 30 ],
			[ '2020-01-01' => 30, '2020-01-02' => 40 ],
			[ 'copyedit' => 30, 'link-recommendation' => 40 ],
			1,
			30,
			wfTimestamp( TS_UNIX, '20200103000000' ),
			[ '2020-01-01' => 1000, '2020-01-02' => 2000 ],
			[
				'Foo' => [ 'views' => [ '2020-01-01' => 500, '2020-01-02' => 1000 ] ],
				'Bar' => [ 'views' => [ '2020-01-01' => 500, '2020-01-02' => 1000 ] ],
			],
			new EditingStreak(),
			0,
			null
		);
		$store->setUserImpact( $expensiveUserImpact );
		$this->assertEquals( $expensiveUserImpact, $store->getUserImpact( $user ) );
		$this->assertEquals( $expensiveUserImpact, $store->getExpensiveUserImpact( $user ) );
	}

	public function testUpdateUserImpact() {
		ConvertibleTimestamp::setFakeTime( time() );
		$store = new DatabaseUserImpactStore( $this->getServiceContainer()->getDBLoadBalancer(), new NullLogger() );
		$user = $this->getTestUser()->getUserIdentity();

		$userImpact = new UserImpact(
			$user,
			100,
			50,
			[ NS_MAIN => 10, NS_USER_TALK => 10 ],
			[ '2020-01-01' => 10, '2020-01-02' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 20 ],
			1,
			10,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			new EditingStreak(),
			0,
			null
		);
		$updatedUserImpact = new UserImpact(
			$user,
			150,
			75,
			[ NS_MAIN => 15, NS_USER_TALK => 15 ],
			[ '2020-01-01' => 15, '2020-01-02' => 25 ],
			[ 'copyedit' => 15, 'link-recommendation' => 25 ],
			2,
			15,
			wfTimestamp( TS_UNIX, '20200101120000' ),
			new EditingStreak(),
			0,
			null
		);

		$store->setUserImpact( $userImpact );
		$this->assertEquals( $userImpact, $store->getUserImpact( $user ) );
		$store->setUserImpact( $updatedUserImpact );
		$this->assertEquals( $updatedUserImpact, $store->getUserImpact( $user ) );
	}

}
