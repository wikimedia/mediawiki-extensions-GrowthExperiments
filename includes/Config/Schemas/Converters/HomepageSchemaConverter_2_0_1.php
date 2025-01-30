<?php

namespace GrowthExperiments\Config\Schemas\Converters;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
use MediaWiki\Extension\CommunityConfiguration\Schema\ISchemaConverter;
use stdClass;

class HomepageSchemaConverter_2_0_1 implements ISchemaConverter {

	public function upgradeFromOlder( stdClass $data ): stdClass {
		unset( $data->GELevelingUpKeepGoingNotificationThresholds );
		return $data;
	}

	public function downgradeToPrevious( stdClass $data ): stdClass {
		$data->GELevelingUpKeepGoingNotificationThresholds = $data->GELevelingUpKeepGoingNotificationThresholdsMaximum;
		return $data;
	}
}
