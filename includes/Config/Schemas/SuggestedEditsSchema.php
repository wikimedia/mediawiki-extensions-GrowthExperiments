<?php

namespace GrowthExperiments\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class SuggestedEditsSchema extends JsonSchema {
	public const GEInfoboxTemplates = [
		self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitles' ],
	];
}
