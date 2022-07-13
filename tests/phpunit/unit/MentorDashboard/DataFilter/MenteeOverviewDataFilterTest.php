<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataFilter;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataFilter
 */
class MenteeOverviewDataFilterTest extends MediaWikiUnitTestCase {

	/** Seconds in a day */
	private const SECONDS_DAY = 86400;

	/** @var array|null */
	private static $testingData = null;

	/**
	 * @param int $secondsAgo
	 * @return string
	 */
	private function getLastActive( int $secondsAgo ): string {
		return ConvertibleTimestamp::convert(
			TS_MW,
			wfTimestamp( TS_UNIX ) - $secondsAgo
		);
	}

	private function getTestingData(): array {
		if ( self::$testingData !== null ) {
			return self::$testingData;
		}

		self::$testingData = [
			[
				'username' => 'Foo',
				'user_id' => 1,
				'editcount' => 2,
				'questions' => 14,
				'last_active' => $this->getLastActive( self::SECONDS_DAY ),
			],
			[
				'username' => 'Bar',
				'user_id' => 2,
				'editcount' => 42,
				'questions' => 2,
				'last_active' => $this->getLastActive( self::SECONDS_DAY * 4 ),
			],
			[
				'username' => 'Baz',
				'user_id' => 3,
				'editcount' => 54,
				'questions' => 10,
				'last_active' => $this->getLastActive( self::SECONDS_DAY * 10 ),
			],
		];
		return self::$testingData;
	}

	/**
	 * @param array|string $usernames
	 * @return array
	 */
	private function getTestingDataForUsernames( $usernames ): array {
		if ( is_string( $usernames ) ) {
			$usernames = [ $usernames ];
		}

		$testingData = $this->getTestingData();
		$res = [];
		foreach ( $testingData as $userData ) {
			if ( in_array( $userData['username'], $usernames ) ) {
				$res[] = $userData;
			}
		}
		return $res;
	}

	/**
	 * @covers ::minEdits
	 * @covers ::filterInternal
	 * @dataProvider provideDataMinEdits
	 * @param array $expected
	 * @param int|null $minedits
	 */
	public function testMinEdits( $expected, ?int $minedits ) {
		$dataFilter = new MenteeOverviewDataFilter( $this->getTestingData() );
		$dataFilter->minEdits( $minedits );
		$this->assertArrayEquals(
			$expected,
			TestingAccessWrapper::newFromObject( $dataFilter )->filterInternal()
		);
	}

	public function provideDataMinEdits() {
		return [
			[
				[],
				300
			],
			[
				$this->getTestingDataForUsernames( 'Baz' ),
				50
			],
		];
	}

	/**
	 * @covers ::maxEdits
	 * @covers ::filterInternal
	 * @dataProvider provideDataMaxEdits
	 * @param array $expected
	 * @param int|null $maxedits
	 */
	public function testMaxEdits( $expected, ?int $maxedits ) {
		$dataFilter = new MenteeOverviewDataFilter( $this->getTestingData() );
		$dataFilter->maxEdits( $maxedits );
		$this->assertArrayEquals(
			$expected,
			TestingAccessWrapper::newFromObject( $dataFilter )->filterInternal()
		);
	}

	public function provideDataMaxEdits() {
		return [
			[
				[],
				1
			],
			[
				$this->getTestingDataForUsernames( 'Foo' ),
				30
			]
		];
	}

	/**
	 * @covers ::sort
	 * @covers ::filterInternal
	 * @covers ::doSort
	 * @covers ::doSortByNumber
	 * @covers ::doSortByTimestamp
	 * @dataProvider provideDataSort
	 * @param array $expected
	 * @param string $sortBy
	 * @param string $order
	 */
	public function testSort( $expected, string $sortBy, string $order ) {
		$dataFilter = new MenteeOverviewDataFilter( $this->getTestingData() );
		$dataFilter->sort( $sortBy, $order );
		$this->assertArrayEquals(
			$expected,
			TestingAccessWrapper::newFromObject( $dataFilter )->filterInternal()
		);
	}

	public function provideDataSort() {
		return [
			[
				$this->getTestingDataForUsernames( [ 'Foo', 'Bar', 'Baz' ] ),
				MenteeOverviewDataFilter::SORT_BY_EDITCOUNT,
				MenteeOverviewDataFilter::SORT_ORDER_ASCENDING
			],
			[
				$this->getTestingDataForUsernames( [ 'Baz', 'Bar', 'Foo' ] ),
				MenteeOverviewDataFilter::SORT_BY_EDITCOUNT,
				MenteeOverviewDataFilter::SORT_ORDER_DESCENDING
			],
			[
				$this->getTestingDataForUsernames( [ 'Bar', 'Baz', 'Foo' ] ),
				MenteeOverviewDataFilter::SORT_BY_QUESTIONS,
				MenteeOverviewDataFilter::SORT_ORDER_ASCENDING
			],
		];
	}

	public function provideDataActiveDaysAgo() {
		return [
			[
				[],
				1,
			],
			[
				$this->getTestingDataForUsernames( 'Foo' ),
				2,
			],
			[
				$this->getTestingDataForUsernames( 'Foo' ),
				3,
			],
			[
				$this->getTestingDataForUsernames( [ 'Foo', 'Bar' ] ),
				5,
			],
		];
	}

	/**
	 * @covers ::activeDaysAgo
	 * @covers ::filterInternal
	 * @dataProvider provideDataActiveDaysAgo
	 * @param array $expected
	 * @param int $daysAgo
	 */
	public function testActiveDaysAgo( $expected, $daysAgo ) {
		$dataFilter = new MenteeOverviewDataFilter( $this->getTestingData() );
		$dataFilter->activeDaysAgo( $daysAgo );
		$this->assertArrayEquals(
			$expected,
			TestingAccessWrapper::newFromObject( $dataFilter )->filterInternal()
		);
	}
}
