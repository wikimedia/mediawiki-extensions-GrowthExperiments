<?php

namespace GrowthExperiments\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class MentorshipSchema extends JsonSchema {
	public const VERSION = '1.0.0';

	public const GEMentorshipEnabled = [
		self::TYPE => self::TYPE_BOOLEAN,
		self::DEFAULT => false,
	];
	public const GEMentorshipAutomaticEligibility = [
		self::TYPE => self::TYPE_BOOLEAN,
		self::DEFAULT => true,
	];
	public const GEMentorshipMinimumAge = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 90,
		self::MINIMUM => 0,
	];
	public const GEMentorshipMinimumEditcount = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 500,
		self::MINIMUM => 0,
	];
	public const GEPersonalizedPraiseDays = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 7,
		self::MINIMUM => 0,
	];
	public const GEPersonalizedPraiseDefaultNotificationsFrequency = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 168,
		self::MINIMUM => 0,
	];
	public const GEPersonalizedPraiseMaxEdits = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 500,
		self::MINIMUM => 0,
	];
	public const GEPersonalizedPraiseMinEdits = [
		self::TYPE => self::TYPE_INTEGER,
		self::DEFAULT => 8,
		self::MINIMUM => 0,
	];

	public const GEMentorshipStartOptedOutThresholds = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			// The `enabled` row could be replaced with nullable types (T365145)
			'enabled' => [
				self::TYPE => self::TYPE_BOOLEAN,
				self::DEFAULT => false,
			],
			'minEditcount' => [
				self::TYPE => self::TYPE_INTEGER,
				self::MINIMUM => 0,
				self::DEFAULT => 500,
			],
			'minTenureInDays' => [
				self::TYPE => self::TYPE_INTEGER,
				self::MINIMUM => 0,
				self::DEFAULT => 30,
			],
		],
	];
}
