<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider
 */
class UncachedMenteeOverviewDataProviderTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'GEUserImpactMaxArticlesToProcessForPageviews', -1 );
	}

	public function testGetFormattedDataForMentors(): void {
		$mentor = $this->getTestSysop()->getUser();
		/** @var User[] $mentees */
		$mentees = [
			$this->createMentee( $mentor ),
			$this->createMenteeWithEditCount( $mentor, 10 ),
			$this->createMenteeWithBlocks( $mentor, 3 ),
			$this->createMentee( $mentor, [
				'registration' => '20200105000000',
				'edit_count' => 1,
			], 'old user with edit' ),

			// mentees not returned ⬇️
			$this->createMenteeWithRegistration( $mentor, '20200105000000' ),
			$this->createMentee( $mentor, [
				'user_options' => [
					HomepageHooks::HOMEPAGE_PREF_ENABLE => '0',
				],
			], 'homepage disabled' ),
			$this->createMentee( $mentor, [
				'user_options' => [
					MentorManager::MENTORSHIP_ENABLED_PREF => '0',
				],
			], 'Mentorship disabled' ),
			$this->createMentee( $mentor, [
				'user_groups' => [ 'bot' ],
			], 'Bot user' ),
			$this->createMentee( $mentor, [
				'blocked_infinity' => true,
			], 'Infinite blocked' ),
			// TODO: figure out how to actually exercise the check for temp users
		];

		$sut = $this->getDataProvider();

		$actualData = $sut->getFormattedDataForMentor( $mentor );

		$expectedData = [
			$mentees[0]->getId() => [
				'editcount' => 0,
				'blocks' => 0,
				// reverted
				// questions
				// username
				// registration
				// last_edit
				// last_active
			],
			$mentees[1]->getId() => [
				'editcount' => 10,
				'blocks' => 0,
			],
			$mentees[2]->getId() => [
				'editcount' => 0,
				'blocks' => 3,
			],
			$mentees[3]->getId() => [
				'editcount' => 1,
				'blocks' => 0,
				'registration' => '20200105000000'
			],
		];

		$this->assertSameSize( $expectedData, $actualData, "Got these names:\n" .
			implode( "\n", array_map( static fn ( $menteeData ) => $menteeData['username'], $actualData ) ) );
		$this->assertArrayContains( $expectedData, $actualData );
	}

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

	private function createMentee( User $mentor, array $overrides = [], ?string $namePartial = null ): User {
		$mentee = $this->getMutableTestUser(
			$overrides[ 'user_groups' ] ?? [],
			isset( $namePartial ) ? ucfirst( $namePartial ) : null,
		)->getUser();

		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $mentee, HomepageHooks::HOMEPAGE_PREF_ENABLE, '1' );
		if ( isset( $overrides['user_options'] ) ) {
			foreach ( $overrides['user_options'] as $key => $value ) {
				$userOptionsManager->setOption( $mentee, $key, $value );
			}
		}
		$userOptionsManager->saveOptions( $mentee );

		if ( array_key_exists( 'registration', $overrides ) ) {
			$this->setMenteeRegistration(
				$mentee,
				$overrides['registration']
			);

			// user_registration was likely read already, recreate the user
			$mentee = $this->getServiceContainer()->getUserFactory()->newFromId( $mentee->getId() );
		}

		if ( isset( $overrides['edit_count'] ) ) {
			$this->setMenteeEditCount( $mentee, $overrides['edit_count'] );
		}

		if ( isset( $overrides['blocked_infinity'] ) ) {
			$sysop = $this->getTestSysop()->getUser();
			$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
			$blockUserFactory->newBlockUser( $mentee, $sysop, 'infinity' )->placeBlock();
		}

		$this->getMentorStore()->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		return $mentee;
	}

	private function setMenteeEditCount( User $mentee, int $editCount ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => $editCount - 1 ] )
			->where( [ 'user_id' => $mentee->getId() ] )
			->caller( __METHOD__ )
			->execute();

		// TODO: is there a faster way to do this?
		$this->editPage(
			'TestPage',
			'Test content: ' . microtime( true ),
			'Make edit to ensure there is a last edit timestamp',
			0,
			$mentee
		);
	}

	private function createMenteeWithEditCount( User $mentor, int $editcount ): User {
		return $this->createMentee(
			$mentor,
			[ 'edit_count' => $editcount ],
			'editcount ' . $editcount
		);
	}

	private function createMenteeWithBlocks( User $mentor, int $blocks ): User {
		$mentee = $this->createMentee( $mentor, [], 'blocks ' . $blocks );
		$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
		$unblockUserFactory = $this->getServiceContainer()->getUnblockUserFactory();
		$sysop = $this->getTestSysop()->getUser();
		for ( $i = 0; $i < $blocks; $i++ ) {
			$blockUserFactory->newBlockUser( $mentee, $sysop, '1 second' )->placeBlock();
			$unblockUserFactory->newUnblockUser( $mentee, $sysop, '' )->unblock();
		}
		return $mentee;
	}

	private function setMenteeRegistration( User $mentee, ?string $registration ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_registration' => $registration ] )
			->where( [ 'user_id' => $mentee->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function createMenteeWithRegistration( User $mentor, ?string $registration ): User {
		return $this->createMentee(
			$mentor,
			[
				'registration' => $registration,
			],
			'registration ' . $registration
		);
	}

	public function testGetEditCount(): void {
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

	public function testGetUsernames(): void {
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

	public function testGetBlocksForUsers(): void {
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

	public function testGetRegistrationTimestampForUsers(): void {
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
