<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use BagOStuff;
use MediaWiki\User\UserIdentity;
use MWTimestamp;

class CacheStorage {

	private const CACHE_CLASS = 'newcomer-tasks';

	/** @var BagOStuff */
	private $cache;

	/** @var UserIdentity */
	private $userIdentity;

	/**
	 * @param BagOStuff $cache
	 * @param UserIdentity $userIdentity
	 */
	public function __construct( BagOStuff $cache, UserIdentity $userIdentity ) {
		$this->cache = $cache;
		$this->userIdentity = $userIdentity;
	}

	/**
	 * Set the page ID in a storage bin specific to the current user.
	 * @param int $pageId
	 * @return bool
	 */
	public function set( int $pageId ): bool {
		return $this->cache->merge(
			$this->getCacheKey(),
			function ( BagOStuff $cache, $key, $oldVal ) use( $pageId ) {
				$denormalizedOldVal = array_keys( $this->normalizeCacheData( $oldVal ) );
				return array_unique( array_merge( [ $pageId ], $denormalizedOldVal ) );
			},
			$this->cache::TTL_WEEK,
			1,
			$this->cache::WRITE_SYNC
		);
	}

	/**
	 * @return int[] Array of page IDs that the user has visited via clicks in the
	 *   Suggested Edits module.
	 */
	public function get(): array {
		$cacheData = $this->normalizeCacheData( $this->cache->get( $this->getCacheKey() ) );
		return array_keys( $cacheData );
	}

	private function getCacheKey() {
		return $this->cache->makeKey( self::CACHE_CLASS, $this->userIdentity->getId() );
	}

	/**
	 * Make sure the cache data is in a standard format. Deals with cache migrations, and also
	 * handles per-page expiry.
	 * @param array<int,int|array>|false $cacheData Raw cache data.
	 * @return array<int,array> An array of page ID => data. Data fields:
	 *   - type: task type ID. Always present; for the migration period can be null.
	 *   - expires: per-item expiry time, as a unix timestamp. Always present.
	 */
	private function normalizeCacheData( $cacheData ): array {
		if ( $cacheData === false ) {
			return [];
		}

		$newCacheData = [];
		$expires = MWTimestamp::now( TS_UNIX ) + $this->cache::TTL_WEEK;
		foreach ( $cacheData as $key => $val ) {
			if ( is_numeric( $val ) ) {
				$newCacheData[$val] = [ 'type' => null, 'expires' => $expires ];
			} elseif ( $val['expires'] < MWTimestamp::now( TS_UNIX ) ) {
				// expired, skip
			} else {
				$newCacheData[$key] = $val;
			}
		}
		return $newCacheData;
	}

}
