<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Mentorship\Hooks\MentorHooks
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
				'user_editcount' => $editcount
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
	 * @covers ::onUserGetRights
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
}
