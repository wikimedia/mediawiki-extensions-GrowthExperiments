<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\UserImpact\ComputeEditingStreaks;
use GrowthExperiments\UserImpact\EditingStreak;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\UserImpact\ComputeEditingStreaks
 */
class ComputedEditStreakTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getEditingStreaks
	 * @dataProvider provideEditingData
	 */
	public function testGetEditingStreaks( array $editData, array $expectedEditingStreaks ) {
		$actualEditingStreaks = ComputeEditingStreaks::getEditingStreaks( $editData );
		$this->assertArrayEquals( $expectedEditingStreaks, $actualEditingStreaks );
	}

	/**
	 * @covers ::getLongestEditingStreak
	 * @dataProvider provideEditingData
	 */
	public function testGetBestEditingStreak(
		array $editData, array $expectedEditingStreaks, EditingStreak $expectedBestEditingStreak
	) {
		$actualEditingStreak = ComputeEditingStreaks::getLongestEditingStreak( $editData );
		$this->assertEquals( $expectedBestEditingStreak, $actualEditingStreak );
	}

	/**
	 * Data provider for testGetEditingStreaks and testGetBestEditingStreak.
	 *
	 * @return array
	 *  Each element in the array contains:
	 *   * array of editCountByDay data (see UserImpact::getEditCountByDay())
	 *   * an array of expected EditingStreak objects
	 *   * the expected best EditingStreak from the data set
	 * @throws \Exception if DateTime is incorrect in makeDatePeriod
	 */
	public static function provideEditingData(): array {
		return [
			'no-edit-data' => [
				[],
				[ new EditingStreak() ],
				new EditingStreak(),
			],
			'one-edit' => [
				[ '2022-10-19' => 1 ],
				[ new EditingStreak(
					ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-19' ),
					1
				) ],
				new EditingStreak(
					ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-19' ),
					1
				),
			],
			'two-edits' => [
				[
					'2022-10-19' => 1,
					'2022-10-18' => 1,
				],
				[ new EditingStreak(
					ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-18' ),
					2
				) ],
				new EditingStreak(
					ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-18' ),
					2
				),
			],
			'one-edit-non-adjacent-days' => [
				[
					'2022-10-19' => 1,
					'2022-10-16' => 1,
				],
				[
					new EditingStreak(
						ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-19' ),
						1
					),
					new EditingStreak(
						ComputeEditingStreaks::makeDatePeriod( '2022-10-16', '2022-10-16' ),
						1
					),
				],
				new EditingStreak(
					ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-19' ),
					1
				),
			],
			'longest edit streak last in history' => [
				[
					'2022-10-19' => 15,
					'2022-10-17' => 1,
					'2022-10-16' => 2,
					'2022-10-14' => 2,
					'2022-10-13' => 3,
					'2022-10-12' => 7,
				],
				[
					new EditingStreak(
						ComputeEditingStreaks::makeDatePeriod( '2022-10-19', '2022-10-19' ),
						15
					),
					new EditingStreak(
						ComputeEditingStreaks::makeDatePeriod( '2022-10-17', '2022-10-16' ),
						3
					),
					new EditingStreak(
						ComputeEditingStreaks::makeDatePeriod( '2022-10-14', '2022-10-12' ),
						12
					),
				],
				new EditingStreak(
					ComputeEditingStreaks::makeDatePeriod( '2022-10-14', '2022-10-12' ),
					12
				),
			],
			'invalid date' => [
				[ 'foo' => 1 ],
				[ new EditingStreak() ],
				new EditingStreak(),
			],
			'invalid count' => [
				[ '2022-10-01' => 'foo' ],
				[ new EditingStreak() ],
				new EditingStreak(),
			],
			'previous month' => [
				[
					'2022-10-03' => 1,
					'2022-10-01' => 5,
					'2022-09-30' => 4,
				],
				[
					new EditingStreak( ComputeEditingStreaks::makeDatePeriod( '2022-10-03', '2022-10-03' ), 1 ),
					new EditingStreak( ComputeEditingStreaks::makeDatePeriod( '2022-10-01', '2022-09-30' ), 9 ),
				],
				new EditingStreak( ComputeEditingStreaks::makeDatePeriod( '2022-10-01', '2022-09-30' ), 9 ),
			],
		];
	}

}
