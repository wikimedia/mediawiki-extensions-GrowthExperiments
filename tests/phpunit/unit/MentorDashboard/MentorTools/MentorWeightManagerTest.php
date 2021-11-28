<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsManager;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager
 */
class MentorWeightManagerTest extends MediaWikiUnitTestCase {

	private function getMockUserOptionsManager() {
		return $this->getMockBuilder( UserOptionsManager::class )
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
	 * @covers ::getWeightForMentor
	 * @dataProvider dataProviderGet
	 */
	public function testGet( $weight ) {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->getMockUserOptionsManager();
		$userOptionsManager->expects( $this->once() )
			->method( 'getIntOption' )
			->with( $mentor, MentorWeightManager::MENTORSHIP_WEIGHT_PREF )
			->willReturn( $weight );
		$manager = new MentorWeightManager( $userOptionsManager );

		$this->assertEquals(
			$weight,
			$manager->getWeightForMentor( $mentor )
		);
	}

	public function dataProviderGet() {
		return [
			[ 1 ],
			[ 4 ],
			[ 2 ],
		];
	}

	/**
	 * @covers ::getWeightForMentor
	 */
	public function testInvalidGet() {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->getMockUserOptionsManager();
		$userOptionsManager->expects( $this->once() )
			->method( 'getIntOption' )
			->with( $mentor, MentorWeightManager::MENTORSHIP_WEIGHT_PREF )
			->willReturn( 123 );
		$manager = new MentorWeightManager( $userOptionsManager );

		$this->assertEquals(
			MentorWeightManager::MENTORSHIP_DEFAULT_WEIGHT,
			$manager->getWeightForMentor( $mentor )
		);
	}

	/**
	 * @covers ::setWeightForMentor
	 */
	public function testSet() {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->getMockUserOptionsManager();
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $mentor, MentorWeightManager::MENTORSHIP_WEIGHT_PREF, 4 );
		$userOptionsManager->expects( $this->once() )
			->method( 'saveOptions' )
			->with( $mentor );
		$manager = new MentorWeightManager( $userOptionsManager );

		$manager->setWeightForMentor( $mentor, 4 );
	}

	/**
	 * @covers ::setWeightForMentor
	 */
	public function testInvalidSet() {
		$this->expectException( InvalidArgumentException::class );
		$manager = new MentorWeightManager( $this->getMockUserOptionsManager() );
		$manager->setWeightForMentor( $this->getTestMentor(), 123 );
	}
}
