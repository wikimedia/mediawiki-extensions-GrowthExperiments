<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\MenteeGraduation;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\Config\Config;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\Mentorship\MenteeGraduation
 */
class MenteeGraduationTest extends MediaWikiUnitTestCase {

	private const DEFAULT_CONFIG_DATA = [
		'enabled' => true,
		'minEditcount' => 500,
		'minTenureInDays' => 30,
	];

	private function getMockConfig( ?array $configData = null ) {
		$configMock = $this->createMock( Config::class );
		$configMock->expects( $this->once() )
			->method( 'get' )
			->with( 'GEMentorshipStartOptedOutThresholds' )
			->willReturn( (object)array_merge(
				self::DEFAULT_CONFIG_DATA,
				$configData ?? []
			) );
		return $configMock;
	}

	public function testGraduateUser() {
		$user = new UserIdentityValue( 1, 'Mentee' );

		$mentorManager = $this->createNoOpMock( IMentorManager::class, [ 'setMentorshipStateForUser' ] );
		$mentorManager->expects( $this->once() )
			->method( 'setMentorshipStateForUser' )
			->with( $user, IMentorManager::MENTORSHIP_OPTED_OUT );
		$menteeGraduation = new MenteeGraduation(
			$this->getMockConfig(),
			$this->createNoOpMock( UserEditTracker::class ),
			$this->createNoOpMock( UserRegistrationLookup::class ),
			$mentorManager
		);

		$menteeGraduation->graduateUserFromMentorship( $user );
	}

	public static function provideConditionEvaluation() {
		return [
			'no condition' => [ false, 0, '20250909210000', [] ],
			'only editcount' => [ false, 1000, '20250909210000', [] ],
			'only registration' => [ false, 0, '20250801000000', [] ],
			'both conditions' => [ true, 1000, '20250801000000', [] ],
			'both conditions (registration null)' => [ true, 1000, null, [] ],
			'both conditions (registration false)' => [ true, 1000, false, [] ],
			'special editCount (fail)' => [ false, 1000, '20250801000000', [ 'minEditcount' => 2000 ] ],
			'special registration (fail)' => [ false, 1000, '20250801000000', [ 'minTenureInDays' => 90 ] ],
			'special editCount (OK)' => [ true, 2000, '20250801000000', [ 'minEditcount' => 2000 ] ],
			'special registration (OK)' => [ true, 1000, '20250501000000', [ 'minTenureInDays' => 90 ] ],
		];
	}

	/**
	 * @dataProvider provideConditionEvaluation
	 */
	public function testConditionEvaluation(
		bool $expected,
		int $actualEditCount, string|false|null $actualRegistrationTS,
		array $extraConfig
	) {
		ConvertibleTimestamp::setFakeTime( '20250909210000' );
		$user = new UserIdentityValue( 1, 'User' );

		$userEditTracker = $this->createNoOpMock( UserEditTracker::class, [ 'getUserEditCount' ] );
		$userEditTracker->expects( $this->atMost( 1 ) )
			->method( 'getUserEditCount' )
			->with( $user )
			->willReturn( $actualEditCount );

		$userRegistrationLookup = $this->createNoOpMock(
			UserRegistrationLookup::class,
			[ 'getRegistration' ]
		);
		$userRegistrationLookup->expects( $this->atMost( 1 ) )
			->method( 'getRegistration' )
			->with( $user )
			->willReturn( $actualRegistrationTS );

		$menteeGraduation = new MenteeGraduation(
			$this->getMockConfig( $extraConfig ),
			$userEditTracker,
			$userRegistrationLookup,
			$this->createNoOpMock( IMentorManager::class )
		);
		$this->assertSame( $expected, $menteeGraduation->doesUserMeetOptOutConditions( $user ) );
	}

	public static function provideShouldUserBeGraduated() {
		foreach ( self::provideConditionEvaluation() as $caseName => $caseParams ) {
			// idx 4
			$caseParams[] = MentorManager::MENTORSHIP_ENABLED;
			// idx 5
			$caseParams[] = false;
			yield $caseName . ', MENTORSHIP_ENABLED, never changed' => $caseParams;

			// no other combination of state / has changed should meet the conditions
			$caseParams[0] = false;

			// test remaining "never changed" cases
			$caseParams[4] = MentorManager::MENTORSHIP_OPTED_OUT;
			yield $caseName . ', MENTORSHIP_OPTED_OUT, never changed' => $caseParams;

			$caseParams[4] = MentorManager::MENTORSHIP_DISABLED;
			yield $caseName . ', MENTORSHIP_DISABLED, never changed' => $caseParams;

			// test all states for "status was changed"
			$caseParams[5] = true;

			$caseParams[4] = MentorManager::MENTORSHIP_ENABLED;
			yield $caseName . ', MENTORSHIP_ENABLED, status changed' => $caseParams;

			$caseParams[4] = MentorManager::MENTORSHIP_OPTED_OUT;
			yield $caseName . ', MENTORSHIP_OPTED_OUT, status changed' => $caseParams;

			$caseParams[4] = MentorManager::MENTORSHIP_DISABLED;
			yield $caseName . ', MENTORSHIP_DISABLED, status changed' => $caseParams;
		}
	}

	/**
	 * @dataProvider provideShouldUserBeGraduated
	 */
	public function testShouldUserBeGraduated(
		bool $expected,
		int $actualEditCount, string|false|null $actualRegistrationTS,
		array $extraConfig,
		int $mentorshipState, bool $wasMentorshipStatusChanged
	) {
		ConvertibleTimestamp::setFakeTime( '20250909210000' );
		$user = new UserIdentityValue( 1, 'User' );

		$userEditTracker = $this->createNoOpMock( UserEditTracker::class, [ 'getUserEditCount' ] );
		$userEditTracker->expects( $this->atMost( 1 ) )
			->method( 'getUserEditCount' )
			->with( $user )
			->willReturn( $actualEditCount );

		$userRegistrationLookup = $this->createNoOpMock(
			UserRegistrationLookup::class,
			[ 'getRegistration' ]
		);
		$userRegistrationLookup->expects( $this->atMost( 1 ) )
			->method( 'getRegistration' )
			->with( $user )
			->willReturn( $actualRegistrationTS );

		$mentorManager = $this->createNoOpMock(
			MentorManager::class,
			[ 'getMentorshipStateForUser', 'didUserExplicitlyOptIntoMentorship' ]
		);
		$mentorManager->expects( $this->atMost( 1 ) )
			->method( 'getMentorshipStateForUser' )
			->with( $user )
			->willReturn( $mentorshipState );
		$mentorManager->expects( $this->atMost( 1 ) )
			->method( 'didUserExplicitlyOptIntoMentorship' )
			->with( $user )
			->willReturn( $wasMentorshipStatusChanged );

		$menteeGraduation = new MenteeGraduation(
			$this->getMockConfig( $extraConfig ),
			$userEditTracker,
			$userRegistrationLookup,
			$mentorManager
		);
		$this->assertSame( $expected, $menteeGraduation->shouldUserBeGraduated( $user ) );
	}
}
