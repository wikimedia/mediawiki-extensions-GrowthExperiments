<?php

namespace GrowthExperiments\Config\Validation;

use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\Schemas\SuggestedEditsSchema;
use InvalidArgumentException;
use MediaWiki\Message\Message;
use StatusValue;

/**
 * Validation class for MediaWiki:GrowthExperimentsConfig.json
 */
class GrowthConfigValidation implements IConfigValidator {
	use DatatypeValidationTrait;

	/**
	 * Copy of TemplateCollectionFeature::MAX_TEMPLATES_IN_COLLECTION. We avoid a direct reference
	 * to keep CirrusSearch an optional dependency.
	 */
	public const MAX_TEMPLATES_IN_COLLECTION = SuggestedEditsSchema::MAX_INFOBOX_TEMPLATES;

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
			'GEHelpPanelLinks' => [
				'type' => 'array<int,array<string,string>>',
			],
			'GEHomepageSuggestedEditsIntroLinks' => [
				'type' => 'array<string,string>',
			],
			'GEInfoboxTemplates' => [
				'type' => 'array',
				'maxSize' => self::MAX_TEMPLATES_IN_COLLECTION,
			],
			'GEInfoboxTemplatesTest' => [
				'type' => 'array',
				'maxSize' => self::MAX_TEMPLATES_IN_COLLECTION,
			],
			'GECampaigns' => [
				'type' => 'array',
			],
			'GECampaignTopics' => [
				'type' => 'array',
			],
			'GEMentorshipAutomaticEligibility' => [
				'type' => 'bool',
			],
			'GEMentorshipMinimumAge' => [
				'type' => 'int',
			],
			'GEMentorshipMinimumEditcount' => [
				'type' => 'int',
			],
			'GEPersonalizedPraiseDefaultNotificationsFrequency' => [
				'type' => 'int',
			],
			'GEPersonalizedPraiseDays' => [
				'type' => 'int',
			],
			'GEPersonalizedPraiseMinEdits' => [
				'type' => 'int',
			],
			'GEPersonalizedPraiseMaxEdits' => [
				'type' => 'int',
			],
			'GEPersonalizedPraiseMaxReverts' => [
				'type' => '?int',
			],
			'GELevelingUpGetStartedMaxTotalEdits' => [
				'type' => 'int',
			],
		];
	}

	/**
	 * Validate a given field
	 *
	 * @param string $fieldName Name of the field to be validated
	 * @param array $descriptor Descriptor of the field
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

		$expectedType = $descriptor['type'];
		if ( !$this->validateFieldDatatype( $expectedType, $value ) ) {
			return StatusValue::newFatal(
				'growthexperiments-config-validator-datatype-mismatch',
				$fieldName,
				$expectedType,
				gettype( $value )
			);
		}

		if ( isset( $descriptor['maxSize'] ) && count( $value ) > $descriptor['maxSize'] ) {
			return StatusValue::newFatal(
				'growthexperiments-config-validator-array-toobig',
				$fieldName,
				Message::numParam( $descriptor['maxSize'] )
			);
		}

		return StatusValue::newGood();
	}

	/**
	 * @inheritDoc
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

	/**
	 * @inheritDoc
	 */
	public function getDefaultContent(): array {
		return [];
	}
}
