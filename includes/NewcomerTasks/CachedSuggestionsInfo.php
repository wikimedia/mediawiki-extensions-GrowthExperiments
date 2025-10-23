<?php

namespace GrowthExperiments\NewcomerTasks;

use Wikimedia\ObjectCache\WANObjectCache;

/**
 * A CachedSuggestionsInfo decorator which uses WANObjectCache to get/set information about tasks
 */
class CachedSuggestionsInfo implements NewcomerTasksInfo {

	/**
	 * @var SuggestionsInfo
	 */
	private $suggestionsInfo;

	/**
	 * @var WANObjectCache
	 */
	private $cache;

	public function __construct(
		SuggestionsInfo $suggestionsInfo,
		WANObjectCache $cache
	) {
		$this->suggestionsInfo = $suggestionsInfo;
		$this->cache = $cache;
	}

	/** @inheritDoc */
	public function getInfo( array $options = [] ) {
		// Force the value to be regenerated if the cached value should not be used
		$resetCache = $options[ 'resetCache' ] ?? false;
		$cacheOption = $resetCache ? [ 'minAsOf' => INF ] : [];
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'growthexperiments-SuggestionsInfo' ),
			$this->cache::TTL_HOUR,
			function ( $oldValue, &$ttl, &$setOpts ) {
				$data = $this->suggestionsInfo->getInfo();
				if ( !$data || isset( $data['error'] ) ) {
					$ttl = $this->cache::TTL_UNCACHEABLE;
				} else {
					// WANObjectCache::fetchOrRegenerate would set this to the start of callback
					// execution if unset. If at the end of the callback more than a few seconds
					// have passed since the given time, it will refuse to cache.
					$setOpts['since'] = INF;
				}
				return $data;
			},
			$cacheOption
		);
	}

}
