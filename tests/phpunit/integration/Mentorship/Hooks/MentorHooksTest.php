<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWikiIntegrationTestCase;
use User;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

/**
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Mentorship\Hooks\MentorHooks
 */
class MentorHooksTest extends MediaWikiIntegrationTestCase {

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
			'wgGEMentorProvider' => MentorProvider::PROVIDER_STRUCTURED,
			'wgGEMentorshipAutomaticEligibility' => true,
			'wgGEMentorshipMinimumAge' => $minAge,
			'wgGEMentorshipMinimumEditcount' => $minEditcount
		] );

		$minAcceptableRegistration = time() - $minAge * ExpirationAwareness::TTL_DAY;
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

	public function provideOnUserGetRights() {
		return [
			[ 4, 10 ],
			[ 10, 4 ],
		];
	}
}
