<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\WikiConfigException;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\StaticMentorManager
 */
class StaticMentorManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getMentorForUser
	 */
	public function testGetMentorForUser() {
		$mentor1 = new Mentor(
			new UserIdentityValue( 12, 'FooMentor' ),
			'text 1',
			'',
			true,
			IMentorWeights::WEIGHT_NORMAL
		);
		$mentor2 = new Mentor(
			new UserIdentityValue( 13, 'BarMentor' ),
			'text 2',
			'',
			true,
			IMentorWeights::WEIGHT_NORMAL
		);
		$mentorManager = new StaticMentorManager( [ 'Foo' => $mentor1, 'Bar' => $mentor2 ] );
		$this->assertSame( $mentor1, $mentorManager->getMentorForUser( new UserIdentityValue( 21, 'Foo' ) ) );
		$this->assertSame( $mentor2, $mentorManager->getMentorForUser( new UserIdentityValue( 22, 'Bar' ) ) );
		$this->expectException( WikiConfigException::class );
		$this->assertSame( null, $mentorManager->getMentorForUser( new UserIdentityValue( 23, 'Baz' ) ) );
	}

	/**
	 * @covers ::getMentorForUserSafe
	 */
	public function testGetMentorForUserSafe() {
		$mentor1 = new Mentor(
			new UserIdentityValue( 12, 'FooMentor' ),
			'text 1',
			'',
			true,
			IMentorWeights::WEIGHT_NORMAL
		);
		$mentor2 = new Mentor(
			new UserIdentityValue( 13, 'BarMentor' ),
			'text 2',
			'',
			true,
			IMentorWeights::WEIGHT_NORMAL
		);
		$mentorManager = new StaticMentorManager( [ 'Foo' => $mentor1, 'Bar' => $mentor2 ] );
		$this->assertSame( $mentor1, $mentorManager->getMentorForUserSafe( new UserIdentityValue( 21, 'Foo' ) ) );
		$this->assertSame( $mentor2, $mentorManager->getMentorForUserSafe( new UserIdentityValue( 22, 'Bar' ) ) );
		$this->assertSame( null, $mentorManager->getMentorForUserSafe( new UserIdentityValue( 23, 'Baz' ) ) );
	}

}
