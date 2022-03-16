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
}
