<?php

namespace GrowthExperiments\Config\Validation;

use StatusValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Interim solution to re-use validation logic in MentorStatusManager and StructuredMentorListValidator,
 * should be part of the validator once manager is re-purposed
 */
class StatusAwayValidator {

	// Using static variables for compatible alternative to constants
	/** @var int Also hardcoded in AwaySettingsDialog.js */
	public static int $maxBackInDays = 365;
	/** @var int Number of seconds in a day */
	public static int $secondsDay = 86400;

	/**
	 * Check whether the timestamp is lesser than MAX_BACK_IN_DAYS setting
	 *
	 * @param string $timestamp
	 * @param int $userId User ID of the user the timestamp is being validated
	 * @return StatusValue
	 */
	public static function validateTimestamp( string $timestamp, int $userId ): StatusValue {
		$converted = ConvertibleTimestamp::convert( TS_UNIX, $timestamp );
		if ( !$converted ) {
			return StatusValue::newFatal(
				'growthexperiments-mentor-list-datatype-mismatch-not-convertible-timestamp',
				$timestamp,
				$userId
			);
		}
		if ( (
				(int)$converted - (int)ConvertibleTimestamp::now( TS_UNIX )
			) > self::$maxBackInDays * self::$secondsDay
		) {
			return StatusValue::newFatal(
				'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-error-toohigh',
				self::$maxBackInDays
			);
		}
		return StatusValue::newGood();
	}
}
