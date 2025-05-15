<?php

namespace GrowthExperiments\Config\Schemas\Converters;

use MediaWiki\Extension\CommunityConfiguration\Schema\ISchemaConverter;
use stdClass;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class CommunityUpdatesConverter_2_0_2 implements ISchemaConverter {

	public function upgradeFromOlder( stdClass $data ): stdClass {
		$data->GEHomepageCommunityUpdatesCallToAction = (object)[
			'pageTitle' => '',
			'buttonText' => '',
		];
		return $data;
	}

	public function downgradeToPrevious( stdClass $data ): stdClass {
		unset( $data->GEHomepageCommunityUpdatesCallToAction );
		return $data;
	}
}
