<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsManager;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager
 */
class MentorStatusManagerTest extends MediaWikiUnitTestCase {

	private function getMockUserOptionsManager() {
		return $this->getMockBuilder( UserOptionsManager::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getMockUserFactory() {
		return $this->getMockBuilder( UserFactory::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getTestMentor(): UserIdentity {
		return new UserIdentityValue(
			123,
			'TestMentor'
		);
	}

	/**
	 * @covers ::getMentorBackTimestamp
	 * @dataProvider provideTimestamps
	 */
	public function testGetMentorBackTimestamp( $expectedTS, $rawTS ) {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->getMockUserOptionsManager();
		$userOptionsManager->expects( $this->once() )
			->method( 'getOption' )
			->with( $mentor, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF )
			->willReturn( $rawTS );
		$userFactory = $this->getMockUserFactory();
		$manager = new MentorStatusManager( $userOptionsManager, $userFactory );

		$this->assertEquals(
			$expectedTS,
			$manager->getMentorBackTimestamp( $this->getTestMentor() )
		);
	}

	public function provideTimestamps() {
		return [
			[ null, null ],
			[ null, '20080101000000' ],
			[ '22221231000000', '22221231000000' ]
		];
	}

	/**
	 * @covers ::getMentorStatus
	 * @dataProvider provideTestStatuses
	 */
	public function testGetMentorStatus( $expectedStatus, $rawTS ) {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->getMockUserOptionsManager();
		$userOptionsManager->expects( $this->once() )
			->method( 'getOption' )
			->with( $mentor, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF )
			->willReturn( $rawTS );
		$userFactory = $this->getMockUserFactory();
		$manager = new MentorStatusManager( $userOptionsManager, $userFactory );

		$this->assertEquals(
			$expectedStatus,
			$manager->getMentorStatus( $mentor )
		);
	}

	public function provideTestStatuses() {
		return [
			[ 'active', null ],
			[ 'active', '20080203000000' ],
			[ 'away', '22221231000000' ]
		];
	}

	/**
	 * @covers ::markMentorAsActive
	 */
	public function testMarkMentorAsActive() {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->getMockUserOptionsManager();
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $mentor, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF, null );
		$userFactory = $this->getMockUserFactory();

		$manager = new MentorStatusManager( $userOptionsManager, $userFactory );
		$manager->markMentorAsActive( $mentor );
	}
}
