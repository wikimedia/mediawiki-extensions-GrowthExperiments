<?php

namespace GrowthExperiments\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class SuggestedEditsSchema extends JsonSchema {

	public const GEInfoboxTemplates = [
		self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
	];

	// ***** TEMPLATE BASED TASKS *****
	public const copyedit = [
		self::REF => [ 'class' => GrowthDefinitions::class, 'field' => 'TEMPLATE_BASED_TASK' ],
	];

	public const expand = [
		self::REF => [ 'class' => GrowthDefinitions::class, 'field' => 'TEMPLATE_BASED_TASK' ],
	];

	public const links = [
		self::REF => [ 'class' => GrowthDefinitions::class, 'field' => 'TEMPLATE_BASED_TASK' ],
	];

	public const references = [
		self::REF => [ 'class' => GrowthDefinitions::class, 'field' => 'TEMPLATE_BASED_TASK' ],
	];

	public const update = [
		self::REF => [ 'class' => GrowthDefinitions::class, 'field' => 'TEMPLATE_BASED_TASK' ],
	];

	// ***** STRUCTURED TASKS *****
	public const image_recommendation = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'disabled' => [
				self::TYPE => self::TYPE_BOOLEAN,
				self::DEFAULT => false,
			],
			'excludedTemplates' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
			],
			'excludedCategories' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
			],
			'learnmore' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
			'maxTasksPerDay' => [
				self::TYPE => self::TYPE_INTEGER,
				self::MINIMUM => 1,
			],
		],
	];

	public const section_image_recommendation = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'disabled' => [
				self::TYPE => self::TYPE_BOOLEAN,
				self::DEFAULT => false,
			],
			'excludedTemplates' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
			],
			'excludedCategories' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
			],
			'learnmore' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
			'maxTasksPerDay' => [
				self::TYPE => self::TYPE_INTEGER,
				self::MINIMUM => 1,
			],
		],
	];

	public const link_recommendation = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'disabled' => [
				self::TYPE => self::TYPE_BOOLEAN,
				self::DEFAULT => false,
			],
			'excludedTemplates' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
			],
			'excludedCategories' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
			],
			'learnmore' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
			'maximumLinksToShowPerTask' => [
				self::TYPE => self::TYPE_INTEGER,
				self::MINIMUM => 1,
			],
			'excludedSections' => [
				self::TYPE => self::TYPE_ARRAY,
				self::ITEMS => [
					self::TYPE => self::TYPE_STRING,
				],
				self::DEFAULT => [],
			],
			'maxTasksPerDay' => [
				self::TYPE => self::TYPE_INTEGER,
				self::MINIMUM => 1,
			],
			'underlinkedWeight' => [
				self::TYPE => self::TYPE_NUMBER,
				self::MINIMUM => 0,
				self::MAXIMUM => 1,
			],
			'minimumLinkScore' => [
				self::TYPE => self::TYPE_NUMBER,
				self::MINIMUM => 0,
				self::MAXIMUM => 1,
			],
		],
	];
}
