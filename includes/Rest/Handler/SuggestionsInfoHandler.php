<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Provide information for monitoring suggested edit task pools by type and topic.
 */
class SuggestionsInfoHandler extends SimpleHandler {

	private NewcomerTasksInfo $suggestionsInfo;
	private WANObjectCache $cache;

	public function __construct(
		NewcomerTasksInfo $suggestionsInfo,
		WANObjectCache $cache
	) {
		$this->suggestionsInfo = $suggestionsInfo;
		$this->cache = $cache;
	}

	/** @inheritDoc */
	public function run() {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'GrowthExperiments', 'SuggestionsInfoHandler' ),
			$this->cache::TTL_HOUR,
			function ( $oldValue, &$ttl ) {
				$info = $this->suggestionsInfo->getInfo();
				// Don't cache error responses.
				if ( !$info || isset( $info['error'] ) ) {
					$ttl = $this->cache::TTL_UNCACHEABLE;
				}
				return $info;
			}
		);
	}

}
