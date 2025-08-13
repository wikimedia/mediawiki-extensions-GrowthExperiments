<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use GrowthExperiments\Util;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\Message\MessageValue;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Provide information for monitoring suggested edit task pools by type and topic.
 */
class SuggestionsInfoHandler extends SimpleHandler {

	private NewcomerTasksInfo $suggestionsInfo;
	private WANObjectCache $cache;

	public function __construct(
		NewcomerTasksInfo $suggestionsInfo,
		WANObjectCache $cache,
	) {
		$this->suggestionsInfo = $suggestionsInfo;
		$this->cache = $cache;
	}

	/** @inheritDoc */
	public function run() {
		if ( !Util::isNewcomerTasksAvailable() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'apierror-moduledisabled', [ 'Suggested edits' ] ),
				404
			);
		}
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
