<?php

namespace GrowthExperiments\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class CommunityUpdatesSchema extends JsonSchema {

	public const GEHomepageCommunityUpdatesEnabled = [
		self::TYPE => self::TYPE_BOOLEAN,
		self::DEFAULT => false,
	];

	public const GEHomepageCommunityUpdatesContentTitle = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
		self::MAX_LENGTH => 50
	];

	public const GEHomepageCommunityUpdatesContentBody = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
		self::MAX_LENGTH => 150
	];
}
