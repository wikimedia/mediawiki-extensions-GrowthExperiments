<?php

namespace GrowthExperiments\Tests\Unit\UserImpact;

use GrowthExperiments\UserImpact\EditingStreak;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\UserImpact\EditingStreak
 */
class EditingStreakTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::jsonSerialize
	 */
	public function testEmptySerialization() {
		$this->assertJsonStringEqualsJsonString(
		'[]',
			json_encode( new EditingStreak() )
		);
	}

	/**
	 * @covers ::jsonSerialize
	 */
	public function testSerialization() {
		$this->assertJsonStringEqualsJsonString(
			'{"datePeriod":{"start":"2022-10-19","end":"2022-10-18","days":2},"totalEditCountForPeriod":2}',
			json_encode( new EditingStreak(
				new \DatePeriod(
					new \DateTime( '2022-10-19' ),
					new \DateInterval( 'P1D' ),
					new \DateTime( '2022-10-18' )
				), 2
			) )
		);
	}
}
