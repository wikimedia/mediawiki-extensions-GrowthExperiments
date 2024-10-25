<?php

namespace GrowthExperiments\Config\Schemas;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class SuggestedEditsSchema extends JsonSchema {
	public const VERSION = '1.0.0';

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
				self::DEFAULT => ImageRecommendationTaskType::DEFAULT_SETTINGS[
					ImageRecommendationTaskType::FIELD_MAX_TASKS_PER_DAY
				],
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
				self::DEFAULT => SectionImageRecommendationTaskType::DEFAULT_SETTINGS[
					SectionImageRecommendationTaskType::FIELD_MAX_TASKS_PER_DAY
				],
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
				self::DEFAULT => LinkRecommendationTaskType::DEFAULT_SETTINGS[
					LinkRecommendationTaskType::FIELD_MAX_LINKS_TO_SHOW_PER_TASK
				],
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
				self::DEFAULT => LinkRecommendationTaskType::DEFAULT_SETTINGS[
					LinkRecommendationTaskType::FIELD_MAX_TASKS_PER_DAY
				],
				self::MINIMUM => 1,
			],
			'underlinkedWeight' => [
				self::TYPE => self::TYPE_NUMBER,
				self::DEFAULT => LinkRecommendationTaskType::DEFAULT_SETTINGS[
					LinkRecommendationTaskType::FIELD_UNDERLINKED_WEIGHT
				],
				self::MINIMUM => 0,
				self::MAXIMUM => 1,
			],
			'minimumLinkScore' => [
				self::TYPE => self::TYPE_NUMBER,
				self::DEFAULT => LinkRecommendationTaskType::DEFAULT_SETTINGS[
					LinkRecommendationTaskType::FIELD_MIN_LINK_SCORE
				],
				self::MINIMUM => 0,
				self::MAXIMUM => 1,
			],
		],
	];
}
