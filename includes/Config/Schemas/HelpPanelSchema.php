<?php

namespace GrowthExperiments\Config\Schemas;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

class HelpPanelSchema extends JsonSchema {
	public const GEHelpPanelExcludedNamespaces = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'Namespaces'
		]
	];
	public const GEHelpPanelReadingModeNamespaces = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'Namespaces'
		]
	];
	public const GEHelpPanelSearchNamespaces = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'Namespaces'
		]
	];
	public const GEHelpPanelHelpDeskTitle = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle'
		]
	];

	public const GEHelpPanelLinks = [
		self::TYPE => self::TYPE_ARRAY,
		self::ITEMS => [
			self::TYPE => self::TYPE_OBJECT,
			self::PROPERTIES => [
				'title' => [
					self::REF => [
						'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle'
					]
				],
				'label' => [
					self::TYPE => self::TYPE_STRING
				]
			]
		],
		self::DEFAULT => []
	];
}
