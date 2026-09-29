<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\Mentor
 */
class MentorTest extends MediaWikiUnitTestCase {

	public function testConstruct() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);
		$this->assertInstanceOf( Mentor::class, $mentor );
	}

	public function testGetUserIdentity() {
		$mentorUserIdentity = new UserIdentityValue( 123, 'Mentor' );
		$mentor = new Mentor(
			$mentorUserIdentity,
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		$this->assertTrue( $mentorUserIdentity->equals( $mentor->getUserIdentity() ) );
	}

	/**
	 * @param string|null $introText
	 * @dataProvider provideGetIntroText
	 */
	public function testGetIntroText( ?string $introText ) {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			$introText,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		if ( $introText === null ) {
			$this->assertEquals( 'foo', $mentor->getIntroText() );
			$this->assertFalse( $mentor->hasCustomIntroText() );
		} else {
			$this->assertEquals( $introText, $mentor->getIntroText() );
			$this->assertTrue( $mentor->hasCustomIntroText() );
		}
	}

	public static function provideGetIntroText() {
		return [
			[ null ],
			[ 'custom intro' ],
		];
	}

	/**
	 * @param int $weight
	 * @dataProvider provideGetWeight
	 */
	public function testGetWeight( int $weight ) {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			$weight
		);
		$this->assertEquals( $weight, $mentor->getWeight() );
	}

	public static function provideGetWeight() {
		return [
			[ IMentorWeights::WEIGHT_NONE ],
			[ IMentorWeights::WEIGHT_NORMAL ],
			[ IMentorWeights::WEIGHT_LOW ],
			[ IMentorWeights::WEIGHT_HIGH ],
		];
	}

	public function testWithIntroText() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		$this->assertEquals( 'foo', $mentor->getIntroText() );
		$this->assertFalse( $mentor->hasCustomIntroText() );

		$newMentor = $mentor->withIntroText( 'baz' );
		$this->assertEquals( 'baz', $newMentor->getIntroText() );
		$this->assertTrue( $newMentor->hasCustomIntroText() );

		$this->assertEquals( 'foo', $mentor->getIntroText() );
		$this->assertFalse( $mentor->hasCustomIntroText() );
	}

	public function testWithWeight() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		$this->assertEquals( IMentorWeights::WEIGHT_NORMAL, $mentor->getWeight() );

		$newMentor = $mentor->withWeight( IMentorWeights::WEIGHT_LOW );
		$this->assertEquals( IMentorWeights::WEIGHT_LOW, $newMentor->getWeight() );

		$this->assertEquals( IMentorWeights::WEIGHT_NORMAL, $mentor->getWeight() );
	}

	public function testWithAwayTimestamp() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL,
			'20260101000000'
		);

		$this->assertSame( '20260101000000', $mentor->getStatusAwayTimestamp() );

		$newMentor = $mentor->withAwayTimestamp( '20260202000000' );
		$this->assertSame( '20260202000000', $newMentor->getStatusAwayTimestamp() );
		$this->assertNull( $mentor->withAwayTimestamp( null )->getStatusAwayTimestamp() );

		$this->assertSame( '20260101000000', $mentor->getStatusAwayTimestamp() );
	}
}
