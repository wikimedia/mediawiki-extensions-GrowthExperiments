<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Config\ServiceOptions;

/**
 * Whether the reading recommendations caches may be reused.
 *
 * Every layer of the feature caches for a day or longer, which hides seeded
 * content, configuration changes and interest edits from a development wiki
 * until the next local midnight. Turning $wgGEReadingRecommendationsCacheEnabled
 * off makes them all recompute instead. Because that would make every homepage
 * view rerun all the searches, it is honored only together with
 * $wgGEDeveloperSetup, so a production wiki cannot turn the caches off.
 */
class ReadingRecommendationsCachePolicy {

	public const CONSTRUCTOR_OPTIONS = [
		'GEReadingRecommendationsCacheEnabled',
		'GEDeveloperSetup',
	];

	private readonly bool $bypassCache;

	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->bypassCache = !$options->get( 'GEReadingRecommendationsCacheEnabled' )
			&& $options->get( 'GEDeveloperSetup' );
	}

	public function shouldUseCache(): bool {
		return !$this->bypassCache;
	}
}
