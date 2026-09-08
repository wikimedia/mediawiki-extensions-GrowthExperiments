<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Config\ServiceOptions;

/**
 * Whether the reading recommendations caches may be reused.
 *
 * Every layer of the feature caches for a day or longer, which hides seeded
 * content, configuration changes and interest edits from a development wiki
 * until the next local midnight. Turning GEReadingRecommendationsCacheEnabled
 * off makes them all recompute instead. Because that would make every homepage
 * view rerun all the searches, it is honored only together with
 * GEDeveloperSetup, so a production wiki cannot turn the caches off.
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

	/**
	 * getWithSetCallback() options for one of the caches.
	 *
	 * When the cache is bypassed, an infinite minAsOf makes WANObjectCache
	 * reject any stored value and run the callback, which is its documented way
	 * to force regeneration; WANObjectCache uses it internally for the same
	 * purpose when it schedules an async refresh. The fresh value is still
	 * stored, so the caches stay warm for anything that does not go through
	 * this policy.
	 *
	 * Note that CacheDecorator and CachedSuggestionsInfo moved off minAsOf to
	 * 'touchedCallback' (T414163). That is not the same case: they force the
	 * callback so that it can examine the cached value and usually reuse it,
	 * which needed a TTL_UNCACHEABLE workaround to suppress the pointless
	 * re-store, and 'touchedCallback' expresses "judge this value" properly.
	 * Here there is nothing to judge, no value-dependent decision, and the
	 * re-store is wanted, so minAsOf says what is meant. It also keeps these
	 * regenerations labelled 'renew' rather than 'miss' in the
	 * wanobjectcache_getwithset_seconds stats.
	 *
	 * @param int $version Cache version of the calling cache
	 * @return array{version: int, minAsOf?: float}
	 */
	public function getCacheOptions( int $version ): array {
		$cacheOptions = [ 'version' => $version ];
		if ( $this->bypassCache ) {
			$cacheOptions['minAsOf'] = INF;
		}
		return $cacheOptions;
	}
}
