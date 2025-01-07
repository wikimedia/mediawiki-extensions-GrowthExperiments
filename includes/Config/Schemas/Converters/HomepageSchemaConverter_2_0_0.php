<?php

namespace GrowthExperiments\Config\Schemas\Converters;

use MediaWiki\Extension\CommunityConfiguration\Schema\ISchemaConverter;
use stdClass;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class HomepageSchemaConverter_2_0_0 implements ISchemaConverter {

	public function upgradeFromOlder( stdClass $data ): stdClass {
		$maximumValue = $data->GELevelingUpKeepGoingNotificationThresholds;
		$data->GELevelingUpKeepGoingNotificationThresholdsMaximum = $maximumValue;
		return $data;
	}

	public function downgradeToPrevious( stdClass $data ): stdClass {
		$data->GELevelingUpKeepGoingNotificationThresholds = [
			1,
			$data->GELevelingUpKeepGoingNotificationThresholdsMaximum
		];
		unset( $data->GELevelingUpKeepGoingNotificationThresholdsMaximum );
		return $data;
	}
}
