<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
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
			true,
			MentorWeightManager::WEIGHT_NORMAL
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
			true,
			MentorWeightManager::WEIGHT_NORMAL
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
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);

		if ( $introText === null ) {
			$this->assertEquals( 'foo', $mentor->getIntroText() );
			$this->assertFalse( $mentor->hasCustomIntroText() );
		} else {
			$this->assertEquals( $introText, $mentor->getIntroText() );
			$this->assertTrue( $mentor->hasCustomIntroText() );
		}
	}

	public function provideGetIntroText() {
		return [
			[ null ],
			[ 'custom intro' ],
		];
	}

	/**
	 * @param bool $autoAssigned
	 * @covers ::getAutoAssigned
	 * @dataProvider provideGetAutoAssigned
	 */
	public function testGetAutoAssigned( bool $autoAssigned ) {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			$autoAssigned,
			MentorWeightManager::WEIGHT_NORMAL
		);
		$this->assertEquals( $autoAssigned, $mentor->getAutoAssigned() );
	}

	public function provideGetAutoAssigned() {
		return [
			[ true ],
			[ false ]
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
			true,
			$weight
		);
		$this->assertEquals( $weight, $mentor->getWeight() );
	}

	public function provideGetWeight() {
		return [
			[ MentorWeightManager::WEIGHT_NORMAL ],
			[ MentorWeightManager::WEIGHT_LOW ],
			[ MentorWeightManager::WEIGHT_HIGH ],
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
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);

		$this->assertEquals( 'foo', $mentor->getIntroText() );
		$this->assertFalse( $mentor->hasCustomIntroText() );

		$mentor->setIntroText( 'baz' );
		$this->assertEquals( 'baz', $mentor->getIntroText() );
		$this->assertTrue( $mentor->hasCustomIntroText() );
	}

	/**
	 * @covers ::getAutoAssigned
	 * @covers ::setAutoAssigned
	 */
	public function testSetAutoAssigned() {
		$mentor = new Mentor(
			new UserIdentityValue( 123, 'Mentor' ),
			null,
			'foo',
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);

		$this->assertTrue( $mentor->getAutoAssigned() );

		$mentor->setAutoAssigned( false );
		$this->assertFalse( $mentor->getAutoAssigned() );
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
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);

		$this->assertEquals( MentorWeightManager::WEIGHT_NORMAL, $mentor->getWeight() );

		$mentor->setWeight( MentorWeightManager::WEIGHT_LOW );
		$this->assertEquals( MentorWeightManager::WEIGHT_LOW, $mentor->getWeight() );
	}
}
