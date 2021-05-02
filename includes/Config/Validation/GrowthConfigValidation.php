<?php

namespace GrowthExperiments\Config\Validation;

use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use InvalidArgumentException;
use LogicException;
use StatusValue;

/**
 * Validation class for MediaWiki:GrowthExperimentsConfig.json
 */
class GrowthConfigValidation implements IConfigValidator {
	private function getConfigDescriptors(): array {
		return [
			'GEHelpPanelReadingModeNamespaces' => [
				'type' => 'int[]',
			],
			'GEHelpPanelExcludedNamespaces' => [
				'type' => 'int[]',
			],
			'GEHelpPanelHelpDeskTitle' => [
				'type' => '?string',
			],
			'GEHelpPanelHelpDeskPostOnTop' => [
				'type' => 'bool',
			],
			'GEHelpPanelViewMoreTitle' => [
				'type' => 'string',
			],
			'GEHelpPanelSearchNamespaces' => [
				'type' => 'int[]',
			],
			'GEHelpPanelAskMentor' => [
				'type' => 'bool',
			],
			'GEMentorshipEnabled' => [
				'type' => 'bool',
			],
			'GEHomepageMentorsList' => [
				'type' => '?string',
			],
			'GEHomepageManualAssignmentMentorsList' => [
				'type' => '?string',
			],
			'GEHelpPanelSuggestedEditsPreferredEditor' => [
				'type' => 'string',
			],
			'GEHelpPanelLinks' => [
				'type' => 'array<int,array<string,string>>',
			],
			'GEHomepageSuggestedEditsIntroLinks' => [
				'type' => 'array<string,string>',
			]
		];
	}

	/**
	 * Validate field's datatype
	 *
	 * Unrecognized value of $expectedType makes this function
	 * to treat the validation as successful.
	 *
	 * @param string $expectedType Unsupported datatype will throw
	 * @param mixed $value
	 * @return bool
	 * @throws LogicException in case of unsupported datatype passed via $expectedType
	 */
	private function validateFieldDatatype( string $expectedType, $value ): bool {
		switch ( $expectedType ) {
			case 'bool':
				return is_bool( $value );
			case 'string':
				return is_string( $value );
			case '?string':
				return $value === null || is_string( $value );
			case 'int[]':
				if ( !is_array( $value ) ) {
					// If it is not an array, it cannot be an array of integers
					return false;
				}
				foreach ( $value as $key => $item ) {
					if ( !is_int( $item ) ) {
						return false;
					}
				}
				return true;
			case 'array<string,string>':
				if ( !is_array( $value ) ) {
					// If it is not an array, it cannot be an array of the intended format
					return false;
				}
				foreach ( $value as $key => $item ) {
					if ( !is_string( $key ) || !is_string( $item ) ) {
						return false;
					}
				}
				return true;
			case 'array<int,array<string,string>>':
				if ( !is_array( $value ) ) {
					// If it is not an array, it cannot be an array of the expected format
					return false;
				}
				foreach ( $value as $key => $subarray ) {
					if ( !is_int( $key ) || !is_array( $subarray ) ) {
						return false;
					}
					foreach ( $subarray as $subkey => $item ) {
						if ( !is_string( $subkey ) || !is_string( $item ) ) {
							return false;
						}
					}
				}
				return true;
		}

		// No validation branch was executed, unsupported datatype
		throw new LogicException( 'Unsupported datatype passed to validateFieldDatatype' );
	}

	/**
	 * Validate a given field
	 *
	 * @param string $fieldName Name of the field to be validated
	 * @param array $descriptor Descriptor of the field (
	 * @param array $data
	 * @return StatusValue
	 */
	private function validateField(
		string $fieldName,
		array $descriptor,
		array $data
	): StatusValue {
		// validate is supposed to make sure $data has $field as a key,
		// so this should not throw key errors.
		$value = $data[$fieldName];

		// Check only the datatype for now
		$expectedType = $descriptor['type'];
		if ( !$this->validateFieldDatatype( $expectedType, $value ) ) {
			return StatusValue::newFatal(
				'growthexperiments-config-validator-datatype-mismatch',
				$fieldName,
				$expectedType,
				gettype( $value )
			);
		}

		return StatusValue::newGood();
	}

	/**
	 * Validate config that's going to be saved
	 *
	 * @param array $data
	 * @return StatusValue
	 */
	public function validate( array $data ): StatusValue {
		$status = StatusValue::newGood();
		foreach ( $this->getConfigDescriptors() as $field => $descriptor ) {
			if ( !array_key_exists( $field, $data ) ) {
				// No need to validate something we're not setting
				continue;
			}

			$status->merge( $this->validateField( $field, $descriptor, $data ) );
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	public function validateVariable( string $variable, $value ): void {
		if ( !in_array( $variable, GrowthExperimentsMultiConfig::ALLOW_LIST ) ) {
			throw new InvalidArgumentException(
				'Invalid attempt to set a variable via WikiPageConfigWriter'
			);
		}
	}
}
