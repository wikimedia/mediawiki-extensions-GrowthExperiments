<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Tests\Helpers\CreateMenteeHelpers;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider
 */
class UncachedMenteeOverviewDataProviderTest extends MediaWikiIntegrationTestCase {
	use CreateMenteeHelpers;

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
		$sut->setBatchSize( 3 );

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
			$this->getServiceContainer()->getConnectionProvider(),
			new NullLogger()
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
