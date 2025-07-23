<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Maintenance\PurgeExpiredMentorStatus;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers GrowthExperiments\Maintenance\PurgeExpiredMentorStatus
 * @group Database
 */
class PurgeExpiredMentorStatusTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass(): string {
		return PurgeExpiredMentorStatus::class;
	}

	public function testPurgeExpiredStatus(): void {
		$ccServices = CommunityConfigurationServices::wrap( $this->getServiceContainer() );
		$mentorListProvider = $ccServices->getConfigurationProviderFactory()->newProvider( 'GrowthMentorList' );
		$mentorListProvider->storeValidConfiguration( [
			'Mentors' => [
				'1' => [
					'message' => 'Untouched',
					'weight' => 2,
					'username' => 'Mentor 1',
				],
				'12' => [
					'message' => 'Expired',
					'weight' => 2,
					'username' => 'Mentor 123',
					'awayTimestamp' => '1970-08-12T10:08:17Z',
				],
				'21' => [
					'message' => 'Not expired',
					'weight' => 2,
					'username' => 'Mentor 321',
					'awayTimestamp' => '2025-08-12T10:00:00Z',
				],
			],
		], $this->getTestUser( [ 'interface-admin' ] )->getUser() );
		// Set a time in between 12's mentor timestamp and 21's
		ConvertibleTimestamp::setFakeTime( strtotime( '2025-04-01T00:00Z' ) );
		$this->maintenance->execute();
		$configStatus = $mentorListProvider->loadValidConfigurationUncached();
		$this->assertTrue( $configStatus->isOK() );
		$this->assertEquals( (object)[
			'Mentors' => (object)[
				'1' => (object)[
					'message' => 'Untouched',
					'weight' => 2,
					'username' => 'Mentor 1',
				],
				'12' => (object)[
					'message' => 'Expired',
					'weight' => 2,
					'username' => 'Mentor 123',
				],
				'21' => (object)[
					'message' => 'Not expired',
					'weight' => 2,
					'username' => 'Mentor 321',
					'awayTimestamp' => '2025-08-12T10:00:00Z',
				],
			],
		], $configStatus->getValue() );
	}
}
