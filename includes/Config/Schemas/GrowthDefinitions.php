<?php

namespace GrowthExperiments\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

class GrowthDefinitions extends JsonSchema {

	public const TEMPLATE_BASED_TASK = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'disabled' => [
				self::TYPE => self::TYPE_BOOLEAN,
				self::DEFAULT => false,
			],
			'templates' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
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
		],
		self::ADDITIONAL_PROPERTIES => false,
	];
}
