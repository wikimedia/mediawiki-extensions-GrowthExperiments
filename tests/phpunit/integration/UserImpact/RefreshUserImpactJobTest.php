<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\StaticUserImpactLookup;
use LogicException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \GrowthExperiments\UserImpact\RefreshUserImpactJob
 * @group Database
 */
class RefreshUserImpactJobTest extends MediaWikiIntegrationTestCase {

	public function testRun() {
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$userImpactStore = $growthServices->getUserImpactStore();
		if ( !$userImpactStore instanceof DatabaseUserImpactStore ) {
			$this->markTestSkipped( 'This test requires DatabaseUserImpactStore' );
		}

		$now = '2022-10-10 01:00:00';
		$jobSchedulingTime = '2022-10-10 00:00:00';
		$userIds = [ 1, 2, 3, 4, 5, 6, 7, 8 ];

		$this->setService( 'UserIdentityLookup', $this->getMockUserIdentityLookup() );
		$this->setService( 'GrowthExperimentsUserImpactLookup', new StaticUserImpactLookup(
			array_map( function ( $userId ) use ( $now ) {
				return $this->getUserImpact( $userId, $now );
			}, array_combine( $userIds, $userIds ) )
		) );

		$userImpactStore->setUserImpact( $this->getUserImpact( 1, '2022-10-01 00:00:00' ) );
		$userImpactStore->setUserImpact( $this->getUserImpact( 2, '2022-10-09 12:00:00' ) );
		$userImpactStore->setUserImpact( $this->getUserImpact( 3, '2022-10-10 00:30:00' ) );
		$userImpactStore->setUserImpact( $this->getUserImpact( 5, '2022-10-01 00:00:00' ) );
		$userImpactStore->setUserImpact( $this->getUserImpact( 6, '2022-10-09 12:00:00' ) );
		$userImpactStore->setUserImpact( $this->getUserImpact( 7, '2022-10-10 00:30:00' ) );

		$userImpactData = [
			1 => json_encode( $this->getUserImpact( 1, $jobSchedulingTime ) ),
			2 => json_encode( $this->getUserImpact( 2, $jobSchedulingTime ) ),
			3 => json_encode( $this->getUserImpact( 3, $jobSchedulingTime ) ),
			4 => json_encode( $this->getUserImpact( 4, $jobSchedulingTime ) ),
			5 => null,
			6 => null,
			7 => null,
			8 => null,
		];

		MWTimestamp::setFakeTime( $now );
		$job = new RefreshUserImpactJob( [
			'impactDataBatch' => $userImpactData,
			'staleBefore' => wfTimestamp( TS_UNIX, '2022-10-09 01:00:00' ),
		] );
		$job->run();

		$actualUserImpacts = $userImpactStore->batchGetUserImpact( $userIds );
		$this->assertSame( $userIds, array_keys( $actualUserImpacts ) );
		$this->assertContainsOnlyInstancesOf( ExpensiveUserImpact::class, $actualUserImpacts );
		$expectedTimestamps = [
			1 => $jobSchedulingTime,
			2 => $jobSchedulingTime,
			3 => '2022-10-10 00:30:00',
			4 => $jobSchedulingTime,
			5 => $now,
			6 => '2022-10-09 12:00:00',
			7 => '2022-10-10 00:30:00',
			8 => $now,
		];
		foreach ( $userIds as $i ) {
			$this->assertSame( $i, $actualUserImpacts[$i]->getUser()->getId() );
			$this->assertTimestampSame( wfTimestamp( TS_UNIX, $expectedTimestamps[$i] ),
				$actualUserImpacts[$i]->getGeneratedAt(), "mismatch for user $i" );
		}
	}

	/**
	 * @param int $userId
	 * @param int|string $generatedAt In any wfTimestamp() format
	 * @return ExpensiveUserImpact
	 */
	private function getUserImpact( int $userId, $generatedAt ): ExpensiveUserImpact {
		$oldTime = MWTimestamp::setFakeTime( $generatedAt );
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( $userId, "User$userId" ),
			0, 0, [], [], [], 0,
			0, null, [], [], new EditingStreak(), null
		);
		MWTimestamp::setFakeTime( $oldTime );
		return $userImpact;
	}

	private function getMockUserIdentityLookup(): UserIdentityLookup {
		return new class implements UserIdentityLookup {
			/** @inheritDoc */
			public function getUserIdentityByName(
				string $name,
				int $queryFlags = IDBAccessObject::READ_NORMAL
			): ?UserIdentity {
				throw new LogicException( 'Not implemented' );
			}

			/** @inheritDoc */
			public function getUserIdentityByUserId(
				int $userId,
				int $queryFlags = IDBAccessObject::READ_NORMAL
			): ?UserIdentity {
				return UserIdentityValue::newRegistered( $userId, "User$userId" );
			}

			/** @inheritDoc */
			public function newSelectQueryBuilder(
				$dbOrQueryFlags = IDBAccessObject::READ_NORMAL
			): UserSelectQueryBuilder {
				throw new LogicException( 'Not implemented' );
			}
		};
	}

	private function assertTimestampSame( int $expected, int $actual, ?string $message = null ) {
		$this->assertSame(
			wfTimestamp( TS_DB, $expected ),
			wfTimestamp( TS_DB, $actual ),
			$message
		);
	}

}
