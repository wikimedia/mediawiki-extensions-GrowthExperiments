<?php
namespace GrowthExperiments\Config\Schemas\Converters;

use MediaWiki\Extension\CommunityConfiguration\Schema\ISchemaConverter;
use stdClass;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class SuggestedEditsSchemaConverter_2_0_0 implements ISchemaConverter {
	public function upgradeFromOlder( stdClass $data ): stdClass {
		$data->link_recommendation->maximumEditsTaskIsAvailable = 100;
		return $data;
	}

	public function downgradeToPrevious( stdClass $data ): stdClass {
		unset( $data->link_recommendation->maximumEditsTaskIsAvailable );
		return $data;
	}
}
