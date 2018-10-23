<?php

namespace GrowthExperiments\Html;

use HTMLMultiSelectField;

class HTMLMultiSelectFieldAllowArbitrary extends HTMLMultiSelectField {

	/**
	 * 'allowArbitrary' means all values are accepted as valid.
	 *
	 * @inheritDoc
	 */
	public function validate( $value, $alldata ) {
		return true;
	}

}
