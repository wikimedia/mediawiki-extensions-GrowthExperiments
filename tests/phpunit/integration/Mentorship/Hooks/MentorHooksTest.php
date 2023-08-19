<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use User;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Mentorship\Hooks\MentorHooks
 */
class MentorHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	private function getUserByRegistrationAndEditcount(
		$timestamp,
		int $editcount
	): User {
		$user = $this->getMutableTestUser()->getUser();

		$dbw = $this->getServiceContainer()->getDBLoadBalancer()
			->getConnection( DB_PRIMARY );
		$dbw->update(
			'user',
			[
				'user_registration' => $dbw->timestamp( $timestamp ),
				'user_editcount' => $editcount
			],
			[ 'user_id' => $user->getId() ],
			__METHOD__
		);

		return $this->getServiceContainer()->getUserFactory()
			->newFromId( $user->getId() );
	}

	/**
	 * @param int $minAge
	 * @param int $minEditcount
	 * @dataProvider provideOnUserGetRights
	 * @covers ::onUserGetRights
	 */
	public function testOnUserGetRights(
		int $minAge,
		int $minEditcount
	) {
		$this->setMwGlobals( [
			'wgGEMentorshipAutomaticEligibility' => true,
			'wgGEMentorshipMinimumAge' => $minAge,
			'wgGEMentorshipMinimumEditcount' => $minEditcount
		] );
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

	/**
	 * @covers ::onPageSaveComplete
	 */
	public function testMarkMenteeAsActive() {
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		$mentor = $this->getTestSysop()->getUserIdentity();
		$menteeTestUser = $this->getMutableTestUser();
		$mentorStore->setMentorForUser( $menteeTestUser->getUserIdentity(), $mentor, MentorStore::ROLE_PRIMARY );
		$mentorStore->markMenteeAsInactive( $menteeTestUser->getUserIdentity() );
		$this->assertFalse( $mentorStore->isMenteeActive( $menteeTestUser->getUserIdentity() ) );

		$this->editPage(
			Title::newFromText( 'Sandbox' ),
			'test',
			'',
			NS_MAIN,
			$menteeTestUser->getAuthority()
		);

		$this->assertTrue( $mentorStore->isMenteeActive( $menteeTestUser->getUserIdentity() ) );
	}
}
