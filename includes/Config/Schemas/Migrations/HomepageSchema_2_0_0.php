<?php

namespace GrowthExperiments\Config\Schemas\Migrations;

use GrowthExperiments\Config\Schemas\Converters\HomepageSchemaConverter_2_0_0;
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class HomepageSchema_2_0_0 extends JsonSchema {
	public const VERSION = '2.0.0';
	public const SCHEMA_PREVIOUS_VERSION = '1.0.0';
	public const SCHEMA_NEXT_VERSION = '2.0.1';
	public const SCHEMA_CONVERTER = HomepageSchemaConverter_2_0_0::class;

	public const GEHomepageSuggestedEditsIntroLinks = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'create' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
			'image' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
		],
		self::ADDITIONAL_PROPERTIES => false,
	];

	public const GELevelingUpGetStartedMaxTotalEdits = [
		self::TYPE => self::TYPE_INTEGER,
		// NOTE: zero is used to disable the notification
		self::MINIMUM => 0,
		self::DEFAULT => 10,
	];

	/**
	 * Maximum threshold for "keep going" notifications.
	 * This value determines when users stop receiving "keep going" notifications
	 * after making suggested edits.
	 *
	 * @see LevelingUpManager::KEEP_GOING_NOTIFICATION_THRESHOLD_MINIMUM for fixed minimum value
	 */
	public const GELevelingUpKeepGoingNotificationThresholdsMaximum = [
		self::TYPE => self::TYPE_INTEGER,
		self::MINIMUM => 0,
		self::DEFAULT => 4
	];
}
