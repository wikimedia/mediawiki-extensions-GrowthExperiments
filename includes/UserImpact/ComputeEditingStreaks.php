<?php

namespace GrowthExperiments\UserImpact;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeInterface;
use Exception;

/**
 * Utility class for processing "Editing streaks", which are consecutive days when a user made edits.
 *
 * The minimum unit for an editing streak is a single day. So if a user has made a single edit, the editing streak
 * is for the date of that edit.
 */
class ComputeEditingStreaks {

	/**
	 * Given an array of dates => edit counts (see UserImpact::getEditCountsByDay), generate an array of
	 * EditingStreak objects. An "editing streak" consists of a date range (minimum unit is one day) with a start and
	 * end date, and a total edit count number for that streak.
	 *
	 * @param array $editCountByDay See {@link UserImpact::getEditCountByDay}
	 * @return EditingStreak[]
	 * @throws Exception
	 */
	public static function getEditingStreaks( array $editCountByDay ): array {
		$editingStreaks = [];
		// Create an initial, empty editing streak to start with.
		// This is also needed in the event that $editCountByDay is empty.
		$editingStreak = new EditingStreak();

		// Iterate over each row in the edit count data. At the start of each loop,
		// we have an EditingStreak object. Check if its date is adjacent to the row's
		// date time.
		//  - If it is adjacent, increment the total edit count for the streak, and increment
		//    the end period for the streak.
		//  - if not adjacent, add the editing streak to the list of streaks, and create a new
		//    object
		foreach ( $editCountByDay as $dateStringIndex => $editCountForDate ) {
			try {
				$currentRowDateTime = new DateTime( $dateStringIndex );
			} catch ( \Exception ) {
				// silently discard row if date is invalid.
				continue;
			}
			if ( !is_int( $editCountForDate ) || $editCountForDate === 0 ) {
				// silently discard row if edit count format is invalid or 0.
				continue;
			}

			if ( $editingStreak->getDatePeriod() &&
				$editingStreak->getDatePeriod()->getEndDate() &&
				!self::isDateAdjacent(
					$currentRowDateTime,
					// isDateAdjacent takes two DateTimeInterface objects.
					// getEndDate() can somehow return null (even though constructing a DatePeriod
					// requires an end date?), but anyway, we know there is an end date because
					// we just checked for one directly above this conditional.
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$editingStreak->getDatePeriod()->getEndDate()
				) ) {
				$editingStreaks[] = $editingStreak;
				$editingStreak = new EditingStreak(
					new DatePeriod(
						$currentRowDateTime,
						new DateInterval( 'P1D' ),
						// We need to set an end time, so set it to the current row's date by default.
						$currentRowDateTime
					),
					$editCountForDate
				);
				continue;
			}
			$editingStreak->setTotalEditCountForPeriod(
				$editingStreak->getTotalEditCountForPeriod() + $editCountForDate
			);
			$editingStreak->setDatePeriod(
				new DatePeriod(
					$editingStreak->getDatePeriod() ?
						$editingStreak->getDatePeriod()->getStartDate() :
						$currentRowDateTime,
					new DateInterval( 'P1D' ),
					$currentRowDateTime
				)
			);
		}
		// Add the last processed edit streak to the list.
		// Also catches the special case of a single item in the editCountByDay array.
		$editingStreaks[] = $editingStreak;
		return $editingStreaks;
	}

	/**
	 * Get the editing streak with the most consecutive days in a given set of edit count by day data.
	 *
	 * @param array $editCountByDay
	 * @return EditingStreak
	 * @throws Exception
	 */
	public static function getLongestEditingStreak(
		array $editCountByDay
	): EditingStreak {
		$longestStreak = null;
		$longestDuration = 0;
		foreach ( self::getEditingStreaks( $editCountByDay ) as $streak ) {
			$duration = $streak->getStreakNumberOfDays();
			if ( !$longestStreak || $duration > $longestDuration ) {
				$longestStreak = $streak;
				$longestDuration = $duration;
			}
		}
		return $longestStreak;
	}

	/**
	 * Utility method for constructing a DatePeriod.
	 *
	 * @param string $start ISO 8601 date, e.g. '2022-08-25'.
	 * @param string $end ISO 8601 date
	 * @return DatePeriod
	 * @throws Exception
	 */
	public static function makeDatePeriod( string $start, string $end ): DatePeriod {
		return new DatePeriod(
			new DateTime( $start ),
			new DateInterval( 'P1D' ),
			new DateTime( $end )
		);
	}

	/**
	 * Utility method to see if a given date is adjacent to another one.
	 *
	 * Examples:
	 *  - "2022-10-01" is adjacent to "2022-09-30"
	 *  - "2022-10-01" is not adjacent to "2022-10-03"
	 * @param DateTimeInterface $dateOne
	 * @param DateTimeInterface $dateTwo
	 * @return bool True if adjacent, false otherwise
	 */
	private static function isDateAdjacent(
		DateTimeInterface $dateOne, DateTimeInterface $dateTwo
	): bool {
		return $dateOne->diff( $dateTwo )->days === 1;
	}
}
