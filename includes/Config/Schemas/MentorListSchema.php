<?php

namespace GrowthExperiments\Config\Schemas;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class MentorListSchema extends JsonSchema {

	public const Mentors = [
		self::TYPE => self::TYPE_OBJECT,
		'patternProperties' => [
			'^[0-9]+$' => [
				self::TYPE => self::TYPE_OBJECT,
				self::PROPERTIES => [
					'username' => [
						self::TYPE => self::TYPE_STRING,
					],
					'message' => [
						self::TYPE => [ self::TYPE_STRING, 'null' ],
						self::MAX_LENGTH => MentorProvider::INTRO_TEXT_LENGTH,
					],
					'weight' => [
						self::TYPE => self::TYPE_INTEGER,
					],
					'automaticallyAssigned' => [
						self::TYPE => self::TYPE_BOOLEAN,
					],
					'awayTimestamp' => [
						self::TYPE => self::TYPE_STRING,
					],
				],
				self::ADDITIONAL_PROPERTIES => false,
			],
		],
		self::DEFAULT => [],
		self::ADDITIONAL_PROPERTIES => false,
	];
}
