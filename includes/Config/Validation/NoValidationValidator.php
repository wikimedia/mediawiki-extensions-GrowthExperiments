<?php

namespace GrowthExperiments\Config\Validation;

use StatusValue;

/**
 * A validator that doesn't validate anything (always passes)
 */
class NoValidationValidator implements IConfigValidator {

	/**
	 * @inheritDoc
	 */
	public function validate( array $config ): StatusValue {
		return StatusValue::newGood();
	}

	/**
	 * @inheritDoc
	 */
	public function validateVariable( string $variable, $value ): void {
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultContent(): array {
		return [];
	}
}
