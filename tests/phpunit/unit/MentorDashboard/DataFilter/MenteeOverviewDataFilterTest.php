<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataFilter;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataFilter
 */
class MenteeOverviewDataFilterTest extends MediaWikiUnitTestCase {
	private function getTestingData(): array {
		return [
			[
				'username' => 'Foo',
				'user_id' => 1,
				'editcount' => 2,
				'questions' => 14,
			],
			[
				'username' => 'Bar',
				'user_id' => 2,
				'editcount' => 42,
				'questions' => 2,
			],
			[
				'username' => 'Baz',
				'user_id' => 3,
				'editcount' => 54,
				'questions' => 10,
			],
		];
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
				[
					[
						'username' => 'Baz',
						'user_id' => 3,
						'editcount' => 54,
						'questions' => 10,
					]
				],
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
				[
					[
						'username' => 'Foo',
						'user_id' => 1,
						'editcount' => 2,
						'questions' => 14,
					]
				],
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
				[
					[
						'username' => 'Foo',
						'user_id' => 1,
						'editcount' => 2,
						'questions' => 14,
					],
					[
						'username' => 'Bar',
						'user_id' => 2,
						'editcount' => 42,
						'questions' => 2,
					],
					[
						'username' => 'Baz',
						'user_id' => 3,
						'editcount' => 54,
						'questions' => 10,
					],
				],
				MenteeOverviewDataFilter::SORT_BY_EDITCOUNT,
				MenteeOverviewDataFilter::SORT_ORDER_ASCENDING
			],
			[
				[
					[
						'username' => 'Baz',
						'user_id' => 3,
						'editcount' => 54,
						'questions' => 10,
					],
					[
						'username' => 'Bar',
						'user_id' => 2,
						'editcount' => 42,
						'questions' => 2,
					],
					[
						'username' => 'Foo',
						'user_id' => 1,
						'editcount' => 2,
						'questions' => 14,
					],
				],
				MenteeOverviewDataFilter::SORT_BY_EDITCOUNT,
				MenteeOverviewDataFilter::SORT_ORDER_DESCENDING
			],
			[
				[
					[
						'username' => 'Bar',
						'user_id' => 2,
						'editcount' => 42,
						'questions' => 2,
					],
					[
						'username' => 'Baz',
						'user_id' => 3,
						'editcount' => 54,
						'questions' => 10,
					],
					[
						'username' => 'Foo',
						'user_id' => 1,
						'editcount' => 2,
						'questions' => 14,
					],
				],
				MenteeOverviewDataFilter::SORT_BY_QUESTIONS,
				MenteeOverviewDataFilter::SORT_ORDER_ASCENDING
			],
		];
	}
}
