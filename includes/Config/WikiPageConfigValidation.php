<?php

namespace GrowthExperiments\Config;

use LogicException;
use StatusValue;

class WikiPageConfigValidation {
	/**
	 * Returns form field in the same form as FormSpecialPage::getFormFields
	 *
	 * This needs to be in line with GrowthExperimentsMultiConfig::ALLOW_LIST
	 *
	 * @see FormSpecialPage::getFormFields()
	 *
	 * @return array
	 */
	public function getFormDescriptors(): array {
		return [
			'GEHelpPanelReadingModeNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-reading-namespaces',
				'section' => 'help-panel',
			],
			'GEHelpPanelExcludedNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-disabled-namespaces',
				'section' => 'help-panel',
			],
			'GEHelpPanelHelpDeskTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-help-panel-helpdesk-title',
				'required' => false,
				'section' => 'help-panel',
			],
			'GEHelpPanelHelpDeskPostOnTop' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-help-panel-post-on-top',
				'section' => 'help-panel',
			],
			'GEHelpPanelViewMoreTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-help-panel-view-more',
				'required' => false,
				'section' => 'help-panel',
			],
			'GEHelpPanelSearchNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-searched-namespaces',
				'section' => 'help-panel',
			],
			'GEHelpPanelAskMentor' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-help-panel-ask-mentor',
				'section' => 'help-panel',
			],
			'GEHomepageTutorialTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-tutorial-title',
				'required' => false,
				'section' => 'homepage',
			],
			'GEMentorshipEnabled' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-mentorship-enabled',
				'section' => 'mentorship',
			],
			'GEHomepageMentorsList' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-mentorship-list-of-auto-assigned-mentors',
				'required' => false,
				'section' => 'mentorship',
			],
			'GEHomepageManualAssignmentMentorsList' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-mentorship-list-of-manually-assigned-mentors',
				'required' => false,
				'section' => 'mentorship',
			],
		];
	}

	/**
	 * Get datatype from a form field type
	 *
	 * Ideally, this should provide a meaningful output for all values used
	 * as type in self::getFormDescriptors(), but this is not enforced. The default
	 * is null, which means "no datatype validation".
	 *
	 * @param string $typeString
	 * @return string|null
	 */
	private function getNativeDatatype( string $typeString ): ?string {
		switch ( $typeString ) {
			case 'check':
				return 'bool';
			case 'title':
				return 'string';
			case 'namespacesmultiselect':
				return 'int[]';
		}

		return null;
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
		$expectedType = $this->getNativeDatatype( $descriptor['type'] );
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
		foreach ( $this->getFormDescriptors() as $field => $descriptor ) {
			if ( !array_key_exists( $field, $data ) ) {
				// No need to validate something we're not setting
				continue;
			}

			$status->merge( $this->validateField( $field, $descriptor, $data ) );
		}

		return $status;
	}
}
