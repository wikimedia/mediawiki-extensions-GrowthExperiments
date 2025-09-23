<?php

namespace GrowthExperiments\Config\Schemas;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

class HelpPanelSchema extends JsonSchema {
	public const VERSION = '1.0.0';

	public const GEHelpPanelExcludedNamespaces = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'Namespaces',
		],
	];
	public const GEHelpPanelReadingModeNamespaces = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'Namespaces',
		],
	];
	public const GEHelpPanelSearchNamespaces = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'Namespaces',
		],
	];

	public const GEHelpPanelAskMentor = [
		self::TYPE => self::TYPE_STRING,
		self::ENUM => [ 'mentor-talk-page', 'help-desk-page' ],
		self::DEFAULT => 'mentor-talk-page',
	];

	public const GEHelpPanelHelpDeskTitle = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle',
		],
	];

	public const GEHelpPanelHelpDeskPostOnTop = [
		self::TYPE => self::TYPE_STRING,
		self::ENUM => [ 'top', 'bottom' ],
		self::DEFAULT => 'top',
	];

	public const GEHelpPanelLinks = [
		self::TYPE => self::TYPE_ARRAY,
		self::ITEMS => [
			self::TYPE => self::TYPE_OBJECT,
			self::PROPERTIES => [
				'title' => [
					self::REF => [
						'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle',
					],
				],
				'text' => [
					self::TYPE => self::TYPE_STRING,
				],
			],
		],
		self::DEFAULT => [],
		self::MAX_ITEMS => 10,
	];

	public const GEHelpPanelViewMoreTitle = [
		self::REF => [
			'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle',
		],
	];
}
