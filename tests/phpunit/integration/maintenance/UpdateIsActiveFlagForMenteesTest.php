<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Maintenance\UpdateIsActiveFlagForMentees;
use GrowthExperiments\Tests\Helpers\CreateMenteeHelpers;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\Maintenance\UpdateIsActiveFlagForMentees
 * @group Database
 */
class UpdateIsActiveFlagForMenteesTest extends MaintenanceBaseTestCase {
	use CreateMenteeHelpers;

	private const RC_MAX_AGE = 90 * 24 * 60 * 60;
	private const FAKE_NOW = '20230601000000';
	/** Less than RC_MAX_AGE before FAKE_NOW */
	private const RECENT_TIMESTAMP = '20230501000000';
	/** More than RC_MAX_AGE before FAKE_NOW */
	private const OLD_TIMESTAMP = '20230101000000';

	protected function getMaintenanceClass(): string {
		return UpdateIsActiveFlagForMentees::class;
	}

	public function testExecute(): void {
		// isMenteeActive()/markMenteeAsInactive() read through the WAN cache;
		// make all reads hit the database (as PageUpdatedSubscriberTest does)
		$this->setMainCache( CACHE_NONE );
		$this->overrideConfigValue( MainConfigNames::RCMaxAge, self::RC_MAX_AGE );

		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();
		$mentor = $this->getTestSysop()->getUser();

		ConvertibleTimestamp::setFakeTime( self::OLD_TIMESTAMP );
		$menteeWithOldEdit = $this->createMentee( $mentor, [], 'old edit' );
		$this->assertStatusGood(
			$this->editPage( 'OldEditPage', 'test', '', NS_MAIN, $menteeWithOldEdit )
		);
		$menteeWithOldRegistration = $this->createMentee( $mentor, [], 'old registration' );

		ConvertibleTimestamp::setFakeTime( self::RECENT_TIMESTAMP );
		$menteeWithRecentEdit = $this->createMentee(
			$mentor,
			[ 'registration' => self::OLD_TIMESTAMP ],
			'recent edit'
		);
		$this->assertStatusGood(
			$this->editPage( 'RecentEditPage', 'test', '', NS_MAIN, $menteeWithRecentEdit )
		);
		$menteeWithRecentRegistration = $this->createMentee( $mentor, [], 'recent registration' );
		$menteeWithoutRegistration = $this->createMentee(
			$mentor,
			[ 'registration' => null ],
			'no registration'
		);

		// Run event ingresses queued by the edits (PageLatestRevisionChangedIngress calls
		// markMenteeAsActive) now, so they cannot fire during the maintenance script's
		// transaction rounds and re-activate a mentee the script marked as inactive
		$this->runDeferredUpdates();

		$mentees = [
			$menteeWithOldEdit, $menteeWithOldRegistration, $menteeWithRecentEdit,
			$menteeWithRecentRegistration, $menteeWithoutRegistration,
		];
		foreach ( $mentees as $mentee ) {
			$this->assertTrue(
				$mentorStore->isMenteeActive( $mentee ),
				"Mentee {$mentee->getName()} should be active before the script runs"
			);
		}

		ConvertibleTimestamp::setFakeTime( self::FAKE_NOW );
		// Force multiple batches, including a final partial one
		$this->maintenance->setBatchSize( 4 );
		$this->maintenance->execute();

		// T432959: a run ending with a partial batch used to leave its transaction
		// round open. In production, the maintenance runner's shutdown commit then
		// failed and the batch's writes were rolled back; in this test the writes
		// stay visible on the shared connection, so check the round directly.
		// REVIEW: There probably should be an easier way to catch this...
		$this->assertFalse(
			$this->getServiceContainer()->getDBLoadBalancerFactory()->hasTransactionRound(),
			'execute() should not leave a transaction round open'
		);

		$this->assertTrue(
			$mentorStore->isMenteeActive( $menteeWithRecentEdit ),
			'Mentee with a recent edit should stay active'
		);
		$this->assertTrue(
			$mentorStore->isMenteeActive( $menteeWithRecentRegistration ),
			'Recently registered mentee with no edits should stay active'
		);
		$this->assertFalse(
			$mentorStore->isMenteeActive( $menteeWithOldEdit ),
			'Mentee whose last edit is older than $wgRCMaxAge should be marked as inactive'
		);
		$this->assertFalse(
			$mentorStore->isMenteeActive( $menteeWithOldRegistration ),
			'Mentee with no edits registered more than $wgRCMaxAge ago should be marked as inactive'
		);
		$this->assertFalse(
			$mentorStore->isMenteeActive( $menteeWithoutRegistration ),
			'Mentee with no edits and no known registration should be marked as inactive'
		);
	}
}
