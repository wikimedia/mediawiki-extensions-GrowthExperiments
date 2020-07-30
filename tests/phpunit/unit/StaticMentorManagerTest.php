<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\WikiConfigException;
use PHPUnit\Framework\TestCase;
use User;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\StaticMentorManager
 */
class StaticMentorManagerTest extends TestCase {

	/**
	 * @covers ::getMentorForUser
	 */
	public function testGetMentorForUser() {
		$mentor1 = new Mentor( $this->getUser( 'FooMentor' ), 'text 1' );
		$mentor2 = new Mentor( $this->getUser( 'BarMentor' ), 'text 2' );
		$mentorManager = new StaticMentorManager( [ 'Foo' => $mentor1, 'Bar' => $mentor2 ] );
		$this->assertSame( $mentor1, $mentorManager->getMentorForUser( $this->getUser( 'Foo' ) ) );
		$this->assertSame( $mentor2, $mentorManager->getMentorForUser( $this->getUser( 'Bar' ) ) );
		$this->expectException( WikiConfigException::class );
		$this->assertSame( null, $mentorManager->getMentorForUser( $this->getUser( 'Baz' ) ) );
	}

	/**
	 * @covers ::getMentorForUserSafe
	 */
	public function testGetMentorForUserSafe() {
		$mentor1 = new Mentor( $this->getUser( 'FooMentor' ), 'text 1' );
		$mentor2 = new Mentor( $this->getUser( 'BarMentor' ), 'text 2' );
		$mentorManager = new StaticMentorManager( [ 'Foo' => $mentor1, 'Bar' => $mentor2 ] );
		$this->assertSame( $mentor1, $mentorManager->getMentorForUserSafe( $this->getUser( 'Foo' ) ) );
		$this->assertSame( $mentor2, $mentorManager->getMentorForUserSafe( $this->getUser( 'Bar' ) ) );
		$this->assertSame( null, $mentorManager->getMentorForUserSafe( $this->getUser( 'Baz' ) ) );
	}

	/**
	 * @covers ::getAvailableMentors
	 */
	public function testGetAvailableMentors() {
		$mentorManager = new StaticMentorManager( [
			'Foo' => new Mentor( $this->getUser( 'FooMentor' ), 'text 1' ),
			'Bar' => new Mentor( $this->getUser( 'BarMentor' ), 'text 2' ),
			'Bar2' => new Mentor( $this->getUser( 'BarMentor' ), 'text 2' ),
		] );
		$this->assertSame( [ 'FooMentor', 'BarMentor' ], $mentorManager->getAvailableMentors() );
	}

	/**
	 * Creates a mock user.
	 * @param string $name Must be properly formatted (capitalized, no underscores etc)
	 * @return User
	 */
	private function getUser( string $name ) {
		// Creating a real User object is less hassle than mocking; we have to be careful which
		// constructor to use to avoid invoking name canonization services, though.
		return User::newFromAnyId( null, $name, null );
	}

}
