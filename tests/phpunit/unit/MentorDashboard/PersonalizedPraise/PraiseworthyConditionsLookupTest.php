<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditions;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use MWTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup
 */
class PraiseworthyConditionsLookupTest extends MediaWikiUnitTestCase {

	/**
	 * @param bool $expectedPraiseworthy
	 * @param array $editsByDay
	 * @param int $minEdits
	 * @param int $days
	 * @param int $maxEdits
	 * @dataProvider provideIsMenteePraiseworthy
	 * @covers ::isMenteePraiseworthyForMentor
	 * @covers ::getEditsInDatePeriod
	 * @covers ::buildDatePeriod
	 */
	public function testIsMenteePraiseworthyDefaults(
		bool $expectedPraiseworthy,
		array $editsByDay,
		int $minEdits, int $days, int $maxEdits
	) {
		MWTimestamp::setFakeTime( '20230115235959' );
		$mentee = new UserIdentityValue( 123, 'Mentee' );

		$settingsMock = $this->createMock( PersonalizedPraiseSettings::class );
		$settingsMock->expects( $this->once() )
			->method( 'getPraiseworthyConditions' )
			->willReturn( new PraiseworthyConditions( $maxEdits, $minEdits, $days ) );

		$mentorManagerMock = $this->createMock( MentorManager::class );
		$mentorManagerMock->expects( $this->once() )
			->method( 'getMentorshipStateForUser' )
			->willReturn( MentorManager::MENTORSHIP_ENABLED );

		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->expects( $this->atLeastOnce() )
			->method( 'getBoolOption' )
			->withConsecutive(
				[ $mentee, HomepageHooks::HOMEPAGE_PREF_ENABLE ],
				[ $mentee, PraiseworthyConditionsLookup::WAS_PRAISED_PREF ],
			)
			->willReturnOnConsecutiveCalls( true, false );

		$menteeImpactMock = $this->createMock( UserImpact::class );
		$menteeImpactMock->expects( $this->once() )
			->method( 'getUser' )
			->willReturn( $mentee );
		$menteeImpactMock->expects( $this->once() )
			->method( 'getTotalEditsCount' )
			->willReturn( array_sum( $editsByDay ) );
		$menteeImpactMock->expects( $this->atMost( 1 ) )
			->method( 'getEditCountByDay' )
			->willReturn( $editsByDay );

		$conditionsLookup = new PraiseworthyConditionsLookup(
			$settingsMock,
			$userOptionsLookupMock,
			$mentorManagerMock
		);
		$this->assertEquals(
			$expectedPraiseworthy,
			$conditionsLookup->isMenteePraiseworthyForMentor(
				$menteeImpactMock, new UserIdentityValue( 321, 'Mentor' )
			)
		);
	}

	public function provideIsMenteePraiseworthy() {
		$editsByDay = [
			'2022-01-01' => 100,
			'2023-01-01' => 5,
			'2023-01-15' => 10,
		];

		return [
			[ true, $editsByDay, 1, 1, 500 ],
			[ false, $editsByDay, 11, 1, 500 ],
			[ true, $editsByDay, 11, 15, 500 ],
			[ false, $editsByDay, 110, 15, 500 ],
			[ true, $editsByDay, 16, 400, 500 ],
			[ false, $editsByDay, 16, 400, 115 ],
		];
	}
}
