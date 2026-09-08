<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use DateTimeImmutable;
use DateTimeZone;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * The current date in the wiki's local timezone.
 *
 * Reading recommendations roll over at local midnight. The date string keys
 * the caches and the day number drives the deterministic daily rotation.
 */
class WikiDay {

	private function __construct(
		private readonly string $date,
		private readonly int $dayNumber
	) {
	}

	/**
	 * @param string|null $timezone Value of $wgLocaltimezone; null means UTC.
	 */
	public static function today( ?string $timezone ): self {
		$now = ( new DateTimeImmutable( '@' . ConvertibleTimestamp::time() ) )
			->setTimezone( new DateTimeZone( $timezone ?? 'UTC' ) );
		return new self(
			$now->format( 'Y-m-d' ),
			intdiv( $now->getTimestamp() + $now->getOffset(), 86400 )
		);
	}

	/**
	 * Local date as Y-m-d, for cache keys.
	 */
	public function getDate(): string {
		return $this->date;
	}

	/**
	 * Days since the epoch in local time, for the daily rotation.
	 */
	public function getDayNumber(): int {
		return $this->dayNumber;
	}
}
