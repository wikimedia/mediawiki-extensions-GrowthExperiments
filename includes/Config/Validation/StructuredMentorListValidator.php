<?php

namespace GrowthExperiments\Config\Validation;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use StatusValue;

class StructuredMentorListValidator implements IConfigValidator {
	use DatatypeValidationTrait;

	/** @var string */
	private const TOP_LEVEL_KEY = 'Mentors';

	/**
	 * @var string[]
	 *
	 * A mapping of keys to data types, used for validating mentor JSON object.
	 *
	 * All keys mentioned here will be required.
	 */
	private const MENTOR_KEY_DATATYPES = [
		'message' => '?string',
		'weight' => 'int',
		'automaticallyAssigned' => 'bool',
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
		foreach ( $supportedKeys as $requiredKey ) {
			if ( !array_key_exists( $requiredKey, $mentor ) ) {
				return StatusValue::newFatal(
					'growthexperiments-mentor-list-missing-key',
					$requiredKey
				);
			}
		}

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
		}

		// Code below assumes mentor declarations are syntactically correct.
		$status = StatusValue::newGood();
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
}
