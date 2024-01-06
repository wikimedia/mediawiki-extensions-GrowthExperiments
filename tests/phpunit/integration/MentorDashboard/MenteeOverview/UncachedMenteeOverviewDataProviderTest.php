<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider
 */
class UncachedMenteeOverviewDataProviderTest extends MediaWikiIntegrationTestCase {
	private function getDataProvider(): UncachedMenteeOverviewDataProvider {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		return new UncachedMenteeOverviewDataProvider(
			$geServices->getMentorStore(),
			$this->getServiceContainer()->getChangeTagDefStore(),
			$this->getServiceContainer()->getActorMigration(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getDBLoadBalancerFactory()
		);
	}

	private function getMentorStore(): MentorStore {
		return GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getMentorStore();
	}

	private function createMentee( User $mentor ): User {
		$mentee = $this->getMutableTestUser()->getUser();
		$this->getMentorStore()->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		return $mentee;
	}

	private function createMenteeWithEditCount( User $mentor, int $editcount ): User {
		$mentee = $this->createMentee( $mentor );
		$this->db->update(
			'user',
			[ 'user_editcount' => $editcount ],
			[ 'user_id' => $mentee->getId() ],
			__METHOD__
		);
		return $mentee;
	}

	private function createMenteeWithBlocks( User $mentor, int $blocks ): User {
		$mentee = $this->createMentee( $mentor );
		$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
		$unblockUserFactory = $this->getServiceContainer()->getUnblockUserFactory();
		$sysop = $this->getTestSysop()->getUser();
		for ( $i = 0; $i < $blocks; $i++ ) {
			$blockUserFactory->newBlockUser( $mentee, $sysop, '1 second' )->placeBlock();
			$unblockUserFactory->newUnblockUser( $mentee, $sysop, '' )->unblock();
		}
		return $mentee;
	}

	private function createMenteeWithRegistration( User $mentor, ?string $registration ) {
		$mentee = $this->createMentee( $mentor );
		$this->db->update(
			'user',
			[ 'user_registration' => $registration ],
			[ 'user_id' => $mentee->getId() ],
			__METHOD__
		);

		// user_registration was likely read already, recreate the user
		$mentee = $this->getServiceContainer()->getUserFactory()->newFromId( $mentee->getId() );
		return $mentee;
	}

	/**
	 * @covers ::getEditCountsForUsers
	 */
	public function testGetEditCount() {
		$mentor = $this->getTestSysop()->getUser();
		/** @var User[] $mentees */
		$mentees = [
			$this->createMenteeWithEditCount( $mentor, 10 ),
			$this->createMenteeWithEditCount( $mentor, 12 ),
			$this->createMenteeWithEditCount( $mentor, 23 )
		];

		$dataProvider = TestingAccessWrapper::newFromObject( $this->getDataProvider() );
		$returnedData = $dataProvider->getEditCountsForUsers(
			array_map( static function ( User $mentee ) {
				return $mentee->getId();
			}, $mentees )
		);
		foreach ( $mentees as $mentee ) {
			$this->assertArrayHasKey( $mentee->getId(), $returnedData );
			$this->assertEquals( $mentee->getEditCount(), $returnedData[$mentee->getId()] );
		}
	}

	/**
	 * @covers ::getUsernames
	 */
	public function testGetUsernames() {
		$mentor = $this->getTestUser()->getUser();
		/** @var User[] $mentees */
		$mentees = [
			$this->createMentee( $mentor ),
			$this->createMentee( $mentor ),
			$this->createMentee( $mentor )
		];

		$dataProvider = TestingAccessWrapper::newFromObject( $this->getDataProvider() );
		$returnedData = $dataProvider->getUsernames(
			array_map( static function ( User $mentee ) {
				return $mentee->getId();
			}, $mentees )
		);
		foreach ( $mentees as $mentee ) {
			$this->assertArrayHasKey( $mentee->getId(), $returnedData );
			$this->assertEquals( $mentee->getName(), $returnedData[$mentee->getId()] );
		}
	}

	/**
	 * @covers ::getBlocksForUsers
	 */
	public function testGetBlocksForUsers() {
		$mentor = $this->getTestUser()->getUser();
		$numberOfBlocks = [ 0, 3, 10 ];
		$mentees = [];
		foreach ( $numberOfBlocks as $num ) {
			$mentee = $this->createMenteeWithBlocks( $mentor, $num );
			$mentees[$mentee->getId()] = $num;
		}

		$dataProvider = TestingAccessWrapper::newFromObject( $this->getDataProvider() );
		$returnedData = $dataProvider->getBlocksForUsers( array_keys( $mentees ) );
		foreach ( $mentees as $menteeId => $blocks ) {
			$this->assertArrayHasKey( $menteeId, $returnedData );
			$this->assertEquals( $blocks, $returnedData[$menteeId] );
		}
	}

	/**
	 * @covers ::getRegistrationTimestampForUsers
	 */
	public function testGetRegistrationTimestampForUsers() {
		$mentor = $this->getTestSysop()->getUser();
		/** @var User[] $mentees */
		$mentees = [
			$this->createMenteeWithRegistration( $mentor, null ),
			$this->createMenteeWithRegistration( $mentor, "20200105000000" ),
			$this->createMenteeWithRegistration( $mentor, "20190509000012" )
		];

		$dataProvider = TestingAccessWrapper::newFromObject( $this->getDataProvider() );
		$returnedData = $dataProvider->getRegistrationTimestampForUsers(
			array_map( static function ( User $mentee ) {
				return $mentee->getId();
			}, $mentees )
		);
		foreach ( $mentees as $mentee ) {
			$this->assertArrayHasKey( $mentee->getId(), $returnedData );
			$this->assertEquals( $mentee->getRegistration(), $returnedData[$mentee->getId()] );
		}
	}
}
