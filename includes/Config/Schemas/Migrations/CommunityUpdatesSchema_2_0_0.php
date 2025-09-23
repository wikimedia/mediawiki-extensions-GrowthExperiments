<?php

namespace GrowthExperiments\Config\Schemas\Migrations;

use GrowthExperiments\Config\Schemas\Converters\CommunityUpdatesConverter_2_0_0;
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class CommunityUpdatesSchema_2_0_0 extends JsonSchema {

	public const SCHEMA_PREVIOUS_VERSION = '1.0.0';
	public const SCHEMA_NEXT_VERSION = '2.0.1';
	public const VERSION = '2.0.0';
	public const SCHEMA_CONVERTER = CommunityUpdatesConverter_2_0_0::class;

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

	public const GEHomepageCommunityUpdatesMinEdits = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 0,
		self::MINIMUM => 0,
		self::MAXIMUM => PHP_INT_MAX,
	];
}
