<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Assert\PreconditionException;

/**
 * @covers \GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup
 */
class LastActionTimestampLookupTest extends MediaWikiUnitTestCase {

	private function newLookup(
		?UserRegistrationLookup $userRegistrationLookup = null,
		?UserEditTracker $userEditTracker = null,
		?LoggerInterface $logger = null
	): LastActionTimestampLookup {
		return new LastActionTimestampLookup(
			$userRegistrationLookup ?? $this->createNoOpMock( UserRegistrationLookup::class ),
			$userEditTracker ?? $this->createNoOpMock( UserEditTracker::class ),
			$logger ?? new NullLogger()
		);
	}

	public function testReturnsLatestEditTimestamp() {
		$user = new UserIdentityValue( 1, 'Mentor' );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->expects( $this->once() )
			->method( 'getLatestEditTimestamp' )
			->with( $user )
			->willReturn( '20220101120000' );

		// Registration must not be consulted when an edit timestamp exists.
		$userRegistrationLookup = $this->createNoOpMock( UserRegistrationLookup::class );

		$lookup = $this->newLookup(
			userRegistrationLookup: $userRegistrationLookup,
			userEditTracker: $userEditTracker
		);
		$this->assertSame( '20220101120000', $lookup->getLastActionTimestampForUser( $user ) );
	}

	public function testFallsBackToRegistrationTimestamp() {
		$user = new UserIdentityValue( 1, 'Mentor' );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->expects( $this->once() )
			->method( 'getLatestEditTimestamp' )
			->with( $user )
			->willReturn( false );

		$userRegistrationLookup = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookup->expects( $this->once() )
			->method( 'getRegistration' )
			->with( $user )
			->willReturn( '20200101120000' );

		$lookup = $this->newLookup(
			userRegistrationLookup: $userRegistrationLookup,
			userEditTracker: $userEditTracker
		);
		$this->assertSame( '20200101120000', $lookup->getLastActionTimestampForUser( $user ) );
	}

	public function testReturnsNullAndLogsWhenNoTimestamps() {
		$user = new UserIdentityValue( 1, 'Mentor' );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getLatestEditTimestamp' )->willReturn( false );

		$userRegistrationLookup = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookup->method( 'getRegistration' )->willReturn( null );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with(
				$this->anything(),
				[ 'user' => 'Mentor' ]
			);

		$lookup = $this->newLookup(
			userRegistrationLookup: $userRegistrationLookup,
			userEditTracker: $userEditTracker,
			logger: $logger
		);
		$this->assertNull( $lookup->getLastActionTimestampForUser( $user ) );
	}

	public function testRejectsNonRegisteredUser() {
		$lookup = $this->newLookup();

		$this->expectException( PreconditionException::class );
		$lookup->getLastActionTimestampForUser( new UserIdentityValue( 0, '127.0.0.1' ) );
	}
}
