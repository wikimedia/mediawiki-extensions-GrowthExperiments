<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditions;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup
 */
class PraiseworthyConditionsLookupTest extends MediaWikiUnitTestCase {

	/**
	 * @param bool $expectedPraiseworthy
	 * @param array $editsByDay
	 * @param int $totalReverts
	 * @param int $minEdits
	 * @param int $days
	 * @param int $maxEdits
	 * @param int|null $maxReverts
	 * @dataProvider provideIsMenteePraiseworthy
	 * @covers ::isMenteePraiseworthyForMentor
	 * @covers ::getEditsInDatePeriod
	 * @covers ::buildDatePeriod
	 */
	public function testIsMenteePraiseworthyDefaults(
		bool $expectedPraiseworthy,
		array $editsByDay, int $totalReverts,
		int $minEdits, int $days, int $maxEdits, ?int $maxReverts
	) {
		MWTimestamp::setFakeTime( '20230115235959' );
		$mentee = new UserIdentityValue( 123, 'Mentee' );

		$settingsMock = $this->createMock( PersonalizedPraiseSettings::class );
		$settingsMock->expects( $this->once() )
			->method( 'getPraiseworthyConditions' )
			->willReturn( new PraiseworthyConditions( $maxEdits, $minEdits, $maxReverts, $days ) );

		$mentorManagerMock = $this->createMock( IMentorManager::class );
		$mentorManagerMock->expects( $this->once() )
			->method( 'getMentorshipStateForUser' )
			->willReturn( IMentorManager::MENTORSHIP_ENABLED );

		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->expects( $this->atLeastOnce() )
			->method( 'getBoolOption' )
			->willReturnMap( [
				[ $mentee, HomepageHooks::HOMEPAGE_PREF_ENABLE, IDBAccessObject::READ_NORMAL, true ],
				[ $mentee, PraiseworthyConditionsLookup::WAS_PRAISED_PREF, IDBAccessObject::READ_NORMAL, false ],
			] );

		$userMock = $this->createMock( User::class );
		$userMock->expects( $this->once() )
			->method( 'getBlock' )
			->willReturn( null );
		$userMock->expects( $this->once() )
			->method( 'isNamed' )
			->willReturn( true );
		$userFactoryMock = $this->createMock( UserFactory::class );
		$userFactoryMock->expects( $this->once() )
			->method( 'newFromUserIdentity' )
			->with( $mentee )
			->willReturn( $userMock );

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
		$menteeImpactMock->expects( $this->atMost( 1 ) )
			->method( 'getRevertedEditCount' )
			->willReturn( $totalReverts );

		$conditionsLookup = new PraiseworthyConditionsLookup(
			$settingsMock,
			$userOptionsLookupMock,
			$userFactoryMock,
			$mentorManagerMock
		);
		$this->assertEquals(
			$expectedPraiseworthy,
			$conditionsLookup->isMenteePraiseworthyForMentor(
				$menteeImpactMock, new UserIdentityValue( 321, 'Mentor' )
			)
		);
	}

	public static function provideIsMenteePraiseworthy() {
		$editsByDay = [
			'2022-01-01' => 100,
			'2023-01-01' => 5,
			'2023-01-15' => 10,
		];

		return [
			[ true, $editsByDay, 5, 1, 1, 500, null ],
			[ false, $editsByDay, 5, 11, 1, 500, null ],
			[ true, $editsByDay, 5, 11, 15, 500, null ],
			[ false, $editsByDay, 5, 110, 15, 500, null ],
			[ true, $editsByDay, 0, 16, 400, 500, null ],
			[ false, $editsByDay, 0, 16, 400, 115, null ],
			[ true, $editsByDay, 10, 1, 1, 500, 20 ],
			[ false, $editsByDay, 10, 1, 1, 500, 5 ],
		];
	}
}
