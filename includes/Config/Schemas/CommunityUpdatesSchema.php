<?php

namespace GrowthExperiments\Config\Schemas;

use GrowthExperiments\Config\Schemas\Converters\CommunityUpdatesConverter_2_0_3;
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class CommunityUpdatesSchema extends JsonSchema {

	public const SCHEMA_PREVIOUS_VERSION = '2.0.2';
	public const VERSION = '2.0.3';
	public const SCHEMA_CONVERTER = CommunityUpdatesConverter_2_0_3::class;

	public const GEHomepageCommunityUpdatesEnabled = [
		self::TYPE => self::TYPE_BOOLEAN,
		self::DEFAULT => false,
	];

	public const GEHomepageCommunityUpdatesContentTitle = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
		self::MAX_LENGTH => 50,
	];

	public const GEHomepageCommunityUpdatesThumbnailFile = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'CommonsFile',
		],
	];

	public const GEHomepageCommunityUpdatesThumbnailAddDarkModeBackground = [
		self::TYPE => self::TYPE_BOOLEAN,
		self::DEFAULT => false,
	];

	public const GEHomepageCommunityUpdatesContentBody = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
		self::MAX_LENGTH => 150,
	];

	public const GEHomepageCommunityUpdatesCallToAction = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'pageTitle' => [
				self::REF => [
					'class' => MediaWikiDefinitions::class,
					'field' => 'PageTitle',
				],
				self::DEFAULT => '',
			],
			'buttonText' => [
				self::TYPE => self::TYPE_STRING,
				self::MAX_LENGTH => 30,
				self::DEFAULT => '',
			],
		],
	];

	public const GEHomepageCommunityUpdatesMinEdits = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 0,
		self::MINIMUM => 0,
		self::MAXIMUM => PHP_INT_MAX,
	];
}
