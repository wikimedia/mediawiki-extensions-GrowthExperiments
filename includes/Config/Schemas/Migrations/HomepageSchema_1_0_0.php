<?php

namespace GrowthExperiments\Config\Schemas\Migrations;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class HomepageSchema_1_0_0 extends JsonSchema {
	public const VERSION = '1.0.0';
	public const SCHEMA_NEXT_VERSION = '2.0.0';

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

	// TODO constant name should have "Max" if the minimum is no editable, see T366139
	public const GELevelingUpKeepGoingNotificationThresholds = [
		self::TYPE => self::TYPE_INTEGER,
		self::MINIMUM => 0,
		self::DEFAULT => 4
	];
}
