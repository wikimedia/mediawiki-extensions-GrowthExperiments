<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\UserImpact\UserImpact;
use HashConfig;
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

		$mentorManagerMock = $this->createMock( MentorManager::class );
		$mentorManagerMock->expects( $this->once() )
			->method( 'getMentorshipStateForUser' )
			->willReturn( MentorManager::MENTORSHIP_ENABLED );

		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->expects( $this->atLeastOnce() )
			->method( 'getBoolOption' )
			->with( $mentee, PraiseworthyConditionsLookup::WAS_PRAISED_PREF )
			->willReturn( false );

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
			new HashConfig( [
				'GEPersonalizedPraiseEdits' => $minEdits,
				'GEPersonalizedPraiseDays' => $days,
				'GEPersonalizedPraiseMaxEdits' => $maxEdits,
			] ),
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
