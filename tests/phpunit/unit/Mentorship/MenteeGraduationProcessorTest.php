<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\MenteeGraduation;
use GrowthExperiments\Mentorship\MenteeGraduationProcessor;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \GrowthExperiments\Mentorship\MenteeGraduationProcessor
 */
class MenteeGraduationProcessorTest extends MediaWikiUnitTestCase {

	public function testIsEnabled() {
		$menteeGraduation = $this->createNoOpMock( MenteeGraduation::class, [ 'getIsEnabled' ] );
		$menteeGraduation->expects( $this->once() )
			->method( 'getIsEnabled' )
			->willReturn( false );

		$processor = new MenteeGraduationProcessor(
			new NullLogger(),
			$this->createNoOpMock( MentorStore::class ),
			$menteeGraduation
		);
		$this->assertFalse( $processor->isEnabled() );
	}

	public function testGraduateMentees() {
		$mentor = new UserIdentityValue( 10, 'Mentor' );
		$mentees = [
			new UserIdentityValue( 1, 'User 1' ),
			new UserIdentityValue( 2, 'User 2' ),
			new UserIdentityValue( 3, 'User 3' ),
			new UserIdentityValue( 4, 'User 4' ),
		];

		$store = $this->createNoOpMock( MentorStore::class, [ 'getMenteesByMentor' ] );
		$store->expects( $this->once() )
			->method( 'getMenteesByMentor' )
			->with( $mentor, MentorStore::ROLE_PRIMARY )
			->willReturn( $mentees );

		$menteeGraduation = $this->createNoOpMock(
			MenteeGraduation::class,
			[ 'shouldUserBeGraduated', 'graduateUserFromMentorship' ]
		);
		$menteeGraduation->expects( $this->exactly( 4 ) )
			->method( 'shouldUserBeGraduated' )
			->withConsecutive( ...array_map(
				static fn ( $x ) => [ $x ],
				$mentees
			) )
			->willReturnCallback( static function ( UserIdentity $user ) {
				return $user->getId() >= 3;
			} );
		$menteeGraduation->expects( $this->exactly( 2 ) )
			->method( 'graduateUserFromMentorship' )
			->withConsecutive(
				[ $mentees[2] ],
				[ $mentees[3] ]
			);

		$processor = new MenteeGraduationProcessor(
			new NullLogger(),
			$store,
			$menteeGraduation
		);
		$this->assertSame( 2, $processor->graduateEligibleMenteesByMentor( $mentor ) );
	}
}
