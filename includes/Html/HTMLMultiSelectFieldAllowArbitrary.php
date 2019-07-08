<?php

namespace GrowthExperiments\Html;

use HTMLMultiSelectField;

class HTMLMultiSelectFieldAllowArbitrary extends HTMLMultiSelectField {

	/**
	 * @inheritDoc
	 */
	public function __construct( $params ) {
		parent::__construct( $params );

		$this->mClass .= ' mw-htmlform-multiselect-allow-arbitrary';
	}

	/**
	 * 'allowArbitrary' means all values are accepted as valid.
	 *
	 * @inheritDoc
	 */
	public function validate( $value, $alldata ) {
		return true;
	}

}
