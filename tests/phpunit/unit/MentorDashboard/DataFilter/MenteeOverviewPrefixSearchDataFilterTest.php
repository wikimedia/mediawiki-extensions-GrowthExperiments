<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewPrefixSearchDataFilter;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewPrefixSearchDataFilter
 */
class MenteeOverviewPrefixSearchDataFilterTest extends MediaWikiUnitTestCase {
	private function getDataFilter( array $data ) {
		return new MenteeOverviewPrefixSearchDataFilter( $data );
	}

	/**
	 * @dataProvider provideDataGetUsernames
	 * @param string[] $expected
	 * @param array $data
	 */
	public function testGetUsernames( $expected, $data ) {
		$this->assertArrayEquals(
			$expected,
			$this->getDataFilter( $data['data'] )
				->prefix( $data['prefix'] ?? '' )
				->limit( $data['limit'] ?? 10 )
				->getUsernames()
		);
	}

	public static function provideDataGetUsernames() {
		$data = [
			[ 'username' => 'Joe Loe' ],
			[ 'username' => 'Jane Doe' ],
			[ 'username' => 'Joe Doe' ],
			[ 'username' => 'Testing User' ],
			[ 'username' => 'Testing User 02' ],
			[ 'username' => 'Testing User 03' ],
			[ 'username' => 'Testing User 04' ],
		];
		return [
			[ [
				'Joe Doe',
				'Joe Loe',
			], [
				'data' => $data,
				'prefix' => 'Joe',
			] ],
			[ [
				'Joe Doe',
			], [
				'data' => $data,
				'prefix' => 'Joe',
				'limit' => 1,
			] ],
			[ [
				'Testing User',
				'Testing User 02',
				'Testing User 03',
			], [
				'data' => $data,
				'prefix' => 'Testing',
				'limit' => 3,
			] ],
		];
	}
}
