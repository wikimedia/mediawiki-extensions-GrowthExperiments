<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use BagOStuff;
use MediaWiki\User\UserIdentity;

class CacheStorage implements StorageInterface {

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

	/** @inheritDoc */
	public function set( int $pageId ): bool {
		return $this->cache->merge(
			$this->getCacheKey(),
			function ( BagOStuff $cache, $key, $oldVal ) use( $pageId ) {
				return array_unique( array_merge( [ $pageId ], $oldVal ?: [] ) );
			},
			$this->cache::TTL_WEEK,
			1,
			$this->cache::WRITE_SYNC
		);
	}

	/** @inheritDoc */
	public function get(): array {
		return $this->cache->get( $this->getCacheKey() ) ?: [];
	}

	private function getCacheKey() {
		return $this->cache->makeKey( self::CACHE_CLASS, $this->userIdentity->getId() );
	}
}
