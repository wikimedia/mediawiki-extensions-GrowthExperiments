<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
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
			'foo',
			IMentorWeights::WEIGHT_NORMAL
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
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		$this->assertTrue( $mentorUserIdentity->equals( $mentor->getUserIdentity() ) );
	}

	/**
	 * @param string|null $introText
	 * @dataProvider provideGetIntroText
	 * @covers ::getIntroText
	 * @covers ::hasCustomIntroText
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
	 * @covers ::getWeight
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

	/**
	 * @covers ::getIntroText
	 * @covers ::hasCustomIntroText
	 * @covers ::setIntroText
	 */
	public function testSetIntroText() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		$this->assertEquals( 'foo', $mentor->getIntroText() );
		$this->assertFalse( $mentor->hasCustomIntroText() );

		$mentor->setIntroText( 'baz' );
		$this->assertEquals( 'baz', $mentor->getIntroText() );
		$this->assertTrue( $mentor->hasCustomIntroText() );
	}

	/**
	 * @covers ::getWeight
	 * @covers ::setWeight
	 */
	public function testSetWeight() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			IMentorWeights::WEIGHT_NORMAL
		);

		$this->assertEquals( IMentorWeights::WEIGHT_NORMAL, $mentor->getWeight() );

		$mentor->setWeight( IMentorWeights::WEIGHT_LOW );
		$this->assertEquals( IMentorWeights::WEIGHT_LOW, $mentor->getWeight() );
	}
}
