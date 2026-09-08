<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ReadingRecommendations\WikiDay;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\WikiDay
 */
class WikiDayTest extends MediaWikiUnitTestCase {

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	public function testUtc() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T22:30:00Z' );
		$this->assertSame( '2026-09-01', WikiDay::today( 'UTC' )->getDate() );
	}

	public function testLocalTimezoneFlipsBeforeUtcMidnight() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T22:30:00Z' );
		// Paris is UTC+2 in summer, so local midnight passed at 22:00Z.
		$this->assertSame( '2026-09-02', WikiDay::today( 'Europe/Paris' )->getDate() );
	}

	public function testNullTimezoneBehavesAsUtc() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T22:30:00Z' );
		$this->assertSame( '2026-09-01', WikiDay::today( null )->getDate() );
	}

	public function testDayNumberIncrementsAtLocalMidnight() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T21:59:59Z' );
		$before = WikiDay::today( 'Europe/Paris' )->getDayNumber();
		ConvertibleTimestamp::setFakeTime( '2026-09-01T22:00:00Z' );
		$after = WikiDay::today( 'Europe/Paris' )->getDayNumber();
		$this->assertSame( $before + 1, $after );
	}

	public function testDayNumberDoesNotIncrementAtUtcMidnightOnLocalWiki() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T23:59:59Z' );
		$before = WikiDay::today( 'Europe/Paris' )->getDayNumber();
		ConvertibleTimestamp::setFakeTime( '2026-09-02T00:00:00Z' );
		$after = WikiDay::today( 'Europe/Paris' )->getDayNumber();
		$this->assertSame( $before, $after );
	}
}
