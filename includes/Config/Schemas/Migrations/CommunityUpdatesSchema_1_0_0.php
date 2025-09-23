<?php

namespace GrowthExperiments\Config\Schemas\Migrations;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class CommunityUpdatesSchema_1_0_0 extends JsonSchema {

	public const SCHEMA_NEXT_VERSION = '2.0.0';
	public const VERSION = '1.0.0';

	public const GEHomepageCommunityUpdatesEnabled = [
		self::TYPE => self::TYPE_BOOLEAN,
		self::DEFAULT => false,
	];

	public const GEHomepageCommunityUpdatesContentTitle = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
		self::MAX_LENGTH => 50,
	];

	public const GEHomepageCommunityUpdatesContentBody = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
		self::MAX_LENGTH => 150,
	];
}
