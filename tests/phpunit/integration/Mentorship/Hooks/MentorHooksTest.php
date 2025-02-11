<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Mentorship\Hooks\MentorHooks
 */
class MentorHooksTest extends MediaWikiIntegrationTestCase {
	use CommunityConfigurationTestHelpers;

	private function getUserByRegistrationAndEditcount(
		$timestamp,
		int $editcount
	): User {
		$user = $this->getMutableTestUser()->getUser();

		$dbw = $this->getServiceContainer()->getConnectionProvider()->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [
				'user_registration' => $dbw->timestamp( $timestamp ),
				'user_editcount' => $editcount,
			] )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();

		return $this->getServiceContainer()->getUserFactory()
			->newFromId( $user->getId() );
	}

	/**
	 * @param int $minAge
	 * @param int $minEditcount
	 * @dataProvider provideOnUserGetRights
	 */
	public function testOnUserGetRights(
		int $minAge,
		int $minEditcount
	) {
		$this->overrideProviderConfig( [
			'GEMentorshipAutomaticEligibility' => true,
			'GEMentorshipMinimumAge' => $minAge,
			'GEMentorshipMinimumEditcount' => $minEditcount,
		], 'Mentorship' );
		// Pin time to avoid failure when next second starts - T316154
		$now = strtotime( '2011-04-01T12:00Z' );
		ConvertibleTimestamp::setFakeTime( $now );

		$minAcceptableRegistration = $now - $minAge * ExpirationAwareness::TTL_DAY;
		$notEligibleByAgeUser = $this->getUserByRegistrationAndEditcount(
			$minAcceptableRegistration + 1,
			$minEditcount
		);
		$notEligibleByEditcountUser = $this->getUserByRegistrationAndEditcount(
			$minAcceptableRegistration,
			$minEditcount - 1
		);
		$eligibleUser = $this->getUserByRegistrationAndEditcount(
			$minAcceptableRegistration,
			$minEditcount
		);

		$this->assertFalse( $notEligibleByAgeUser->isAllowed( 'enrollasmentor' ) );
		$this->assertFalse( $notEligibleByEditcountUser->isAllowed( 'enrollasmentor' ) );
		$this->assertTrue( $eligibleUser->isAllowed( 'enrollasmentor' ) );
	}

	public static function provideOnUserGetRights() {
		return [
			[ 4, 10 ],
			[ 10, 4 ],
		];
	}

	public static function provideOnBlockIpComplete() {
		return [
			'Temporary block' => [ true, '1 day' ],
			'Sitewide block' => [ false, 'indefinite' ],
		];
	}

	/**
	 * @dataProvider provideOnBlockIpComplete
	 * @param bool $expectedSurvival
	 * @param string $expiry
	 */
	public function testOnBlockIpComplete( bool $expectedSurvival, string $expiry ) {
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();
		$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();

		$mentee = $this->getTestUser()->getUserIdentity();
		$sysop = $this->getTestSysop()->getUser();
		$mentorStore->setMentorForUser( $mentee, $sysop, MentorStore::ROLE_PRIMARY );

		$blockStatus = $blockUserFactory->newBlockUser( $mentee, $sysop, $expiry )->placeBlock();
		$this->assertStatusOK( $blockStatus, 'Failed to block mentor' );

		if ( $expectedSurvival ) {
			$this->assertEquals( $sysop, $mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
		} else {
			$this->assertNull( $mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
		}
	}
}
