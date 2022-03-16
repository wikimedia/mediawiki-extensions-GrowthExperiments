<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Mentor
 */
class MentorTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo'
		);
		$this->assertInstanceOf( Mentor::class, $mentor );
	}

	/**
	 * @covers ::getUserIdentity
	 */
	public function testGetUserIdentity() {
		$mentorUserIdentity = new UserIdentityValue( 123, 'Mentor' );
		$mentor = new Mentor(
			$mentorUserIdentity,
			null,
			'foo'
		);

		$this->assertTrue( $mentorUserIdentity->equals( $mentor->getUserIdentity() ) );
	}

	/**
	 * @param string|null $introText
	 * @dataProvider provideGetIntroText
	 * @covers ::getIntroText
	 */
	public function testGetIntroText( ?string $introText ) {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			$introText,
			'foo'
		);

		if ( $introText === null ) {
			$this->assertEquals( 'foo', $mentor->getIntroText() );
		} else {
			$this->assertEquals( $introText, $mentor->getIntroText() );
		}
	}

	public function provideGetIntroText() {
		return [
			[ null ],
			[ 'custom intro' ],
		];
	}
}
