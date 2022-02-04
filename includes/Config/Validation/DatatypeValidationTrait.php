<?php

namespace GrowthExperiments\Config\Validation;

use InvalidArgumentException;

trait DatatypeValidationTrait {
	/**
	 * Validate field's datatype
	 *
	 * @param string $expectedType Unsupported datatype will throw
	 * @param mixed $value
	 * @return bool
	 * @throws InvalidArgumentException in case of unsupported datatype passed via $expectedType
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
			case 'array':
				return is_array( $value );
			case '?array':
				return $value === null || is_array( $value );
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
			default:
				// No validation branch was executed, unsupported datatype
				throw new InvalidArgumentException(
					"Unsupported datatype $expectedType passed to validateFieldDatatype"
				);
		}
	}
}
