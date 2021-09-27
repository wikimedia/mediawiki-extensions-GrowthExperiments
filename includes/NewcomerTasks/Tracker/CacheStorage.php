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
	 * Store the page ID and task type of a task card the user has clicked.
	 * The store is tied to the context user.
	 * @param int $pageId
	 * @param string $taskTypeId
	 * @return bool
	 */
	public function set( int $pageId, string $taskTypeId ): bool {
		return $this->cache->merge(
			$this->getCacheKey(),
			function ( BagOStuff $cache, $key, $oldVal ) use( $pageId, $taskTypeId ) {
				$oldVal = $this->normalizeCacheData( $oldVal );
				$expires = (int)MWTimestamp::now( TS_UNIX ) + $this->cache::TTL_WEEK;
				$oldVal[$pageId] = [ 'type' => $taskTypeId, 'expires' => $expires ];
				return $oldVal;
			},
			$this->cache::TTL_WEEK,
			1,
			$this->cache::WRITE_SYNC
		);
	}

	/**
	 * Get an array of task type objects, indexed by page ID, for tasks that the user has opened
	 * via clicks on task cards in the Suggested Edits module.
	 * @return array<int,string|null>
	 */
	public function get(): array {
		$cacheData = $this->normalizeCacheData( $this->cache->get( $this->getCacheKey() ) );
		return array_map( static function ( $item ) {
			return $item['type'];
		}, $cacheData );
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
		foreach ( $cacheData as $key => $val ) {
			// ignore expired items
			if ( $val['expires'] >= MWTimestamp::now( TS_UNIX ) ) {
				$newCacheData[$key] = $val;
			}
		}
		return $newCacheData;
	}

}
