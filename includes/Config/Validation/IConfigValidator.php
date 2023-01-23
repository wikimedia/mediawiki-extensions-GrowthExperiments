<?php

namespace GrowthExperiments\Config\Validation;

use InvalidArgumentException;
use StatusValue;

interface IConfigValidator {
	/**
	 * Validate passed config
	 *
	 * This is executed by WikiPageConfigWriter _before_ writing a config (for edits made
	 * via GrowthExperiments-provided interface), by ConfigHooks for manual edits and
	 * by WikiPageConfigLoader before returning the config (this is to ensure invalid config
	 * is never used).
	 *
	 * @param array $config Associative array representing config that's going to be validated
	 * @return StatusValue
	 */
	public function validate( array $config ): StatusValue;

	/**
	 * Validate an attempt to add a variable
	 *
	 * This is executed by WikiPageConfigWriter _before_ setVariable() runs.
	 *
	 * Implementation should throw InvalidArgumentException on any validation
	 * error.
	 *
	 * @param string $variable
	 * @param mixed $value
	 * @throws InvalidArgumentException In case of a validation error
	 */
	public function validateVariable( string $variable, $value ): void;

	/**
	 * If the configuration page assigned to this validator does not exist, return this
	 *
	 * Useful for ie. structured mentor list, which requires the Mentors key
	 * to be present.
	 *
	 * @return array
	 */
	public function getDefaultContent(): array;
}
