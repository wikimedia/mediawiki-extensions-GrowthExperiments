<?php

namespace GrowthExperiments\Config\Validation;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use MediaWiki\Json\FormatJson;
use StatusValue;

class StructuredMentorListValidator {
	use DatatypeValidationTrait;

	private const TOP_LEVEL_KEY = 'Mentors';

	/**
	 * A mapping of keys to data types, used for validating mentor JSON object.
	 *
	 * All keys mentioned here (except keys listed in OPTIONAL_MENTOR_KEYS) will be required.
	 */
	private const MENTOR_KEY_DATATYPES = [
		'username' => 'string',
		'message' => '?string',
		'weight' => 'int',
		'automaticallyAssigned' => 'bool',
		'awayTimestamp' => '?string',
	];

	/** List of optional keys in mentor serialization. */
	private const OPTIONAL_MENTOR_KEYS = [
		'username',
		'automaticallyAssigned',
		'awayTimestamp',
	];

	/**
	 * @inheritDoc
	 */
	public function validate( array $config ): StatusValue {
		if ( !array_key_exists( self::TOP_LEVEL_KEY, $config ) ) {
			return StatusValue::newFatal(
				'growthexperiments-mentor-list-missing-key',
				self::TOP_LEVEL_KEY
			);
		}

		$mentors = $config[self::TOP_LEVEL_KEY];
		if ( !$this->validateFieldDatatype( 'array<int,array>', $mentors ) ) {
			return StatusValue::newFatal(
				'growthexperiments-mentor-list-datatype-mismatch',
				self::TOP_LEVEL_KEY,
				'array<int,array>',
				gettype( $mentors )
			);
		}

		$status = StatusValue::newGood();
		foreach ( $mentors as $userId => $mentor ) {
			$status->merge( $this->validateMentor(
				$mentor,
				$userId
			) );
		}

		return $status;
	}

	/**
	 * Validate a mentor JSON representation
	 *
	 * @param array $mentor
	 * @param int $userId User ID of the user represented by $mentor
	 * @return StatusValue
	 */
	private function validateMentor( array $mentor, int $userId ): StatusValue {
		$supportedKeys = array_keys( self::MENTOR_KEY_DATATYPES );

		// Ensure all supported keys are present in the mentor object
		foreach ( $supportedKeys as $key ) {
			if ( !array_key_exists( $key, $mentor ) && !in_array( $key, self::OPTIONAL_MENTOR_KEYS ) ) {
				return StatusValue::newFatal(
					'growthexperiments-mentor-list-missing-key',
					$key
				);
			}
		}

		$status = StatusValue::newGood();
		// Ensure all keys present in the mentor object are supported and of correct data type
		foreach ( $mentor as $key => $value ) {
			if ( !array_key_exists( $key, self::MENTOR_KEY_DATATYPES ) ) {
				return StatusValue::newFatal(
					'growthexperiments-mentor-list-unexpected-key-mentor',
					$key
				);
			}

			if ( !$this->validateFieldDatatype( self::MENTOR_KEY_DATATYPES[$key], $value ) ) {
				return StatusValue::newFatal(
					'growthexperiments-mentor-list-datatype-mismatch',
					$key,
					self::MENTOR_KEY_DATATYPES[$key],
					gettype( $value )
				);
			}

			if ( $key === 'weight' && !in_array( $value, IMentorWeights::WEIGHTS ) ) {
				return StatusValue::newFatal(
					'growthexperiments-mentor-list-invalid-weight',
					$key,
					FormatJson::encode( IMentorWeights::WEIGHTS ),
					$value
				);
			}
			if ( $key === 'awayTimestamp' ) {
				$timestampStatus = StatusAwayValidator::validateTimestamp( $value, $userId );
				$status->merge( $timestampStatus );
			}
		}

		// Code below assumes mentor declarations are syntactically correct.
		$status->merge( self::validateMentorMessage( $mentor, $userId ) );
		return $status;
	}

	/**
	 * Validate the mentor message
	 *
	 * Currently only checks MentorProvider::INTRO_TEXT_LENGTH.
	 *
	 * @param array $mentor
	 * @param int $userId User ID of the user represented by $mentor
	 * @return StatusValue Warning means "an issue, but not important enough to stop using the
	 * mentor list".
	 */
	public static function validateMentorMessage(
		array $mentor,
		int $userId
	): StatusValue {
		$status = StatusValue::newGood();

		// Ensure message has correct length. This only warns, as we do not want to fail the
		// validation (truncated message is better than broken mentorship).
		if ( mb_strlen( $mentor['message'] ?? '', 'UTF-8' ) > MentorProvider::INTRO_TEXT_LENGTH ) {
			$status->warning(
				'growthexperiments-mentor-writer-error-message-too-long',
				MentorProvider::INTRO_TEXT_LENGTH,
				$userId
			);
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	public function validateVariable( string $variable, $value ): void {
		if ( $variable !== self::TOP_LEVEL_KEY ) {
			throw new InvalidArgumentException(
				"Invalid variable $variable configured in the mentor list"
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultContent(): array {
		return [ 'Mentors' => [] ];
	}
}
