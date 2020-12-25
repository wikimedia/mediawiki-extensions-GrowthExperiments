<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use ApiRawMessage;
use BagOStuff;
use FormatJson;
use GrowthExperiments\Util;
use HashBagOStuff;
use JsonContent;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\RevisionRecord;
use StatusValue;
use TitleFactory;

/**
 * Helper class for PageConfigurationLoader that loads a specified JSON page.
 * If the passed LinkTarget is an interwiki, it will be loaded via a HTTP request,
 * otherwise by direct database lookup.
 */
class PageLoader {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var BagOStuff */
	private $cache;

	/** @var int Cache expiry (0 for unlimited). */
	private $cacheTtl = 0;

	/**
	 * @param HttpRequestFactory $requestFactory
	 * @param RevisionLookup $revisionLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		HttpRequestFactory $requestFactory,
		RevisionLookup $revisionLookup,
		TitleFactory $titleFactory
	) {
		$this->requestFactory = $requestFactory;
		$this->revisionLookup = $revisionLookup;
		$this->titleFactory = $titleFactory;
		$this->cache = new HashBagOStuff();
	}

	/**
	 * Use a different cache. (Default is in-process caching only.)
	 * @param BagOStuff $cache
	 * @param int $ttl Cache expiry (0 for unlimited).
	 */
	public function setCache( BagOStuff $cache, $ttl ) {
		$this->cache = $cache;
		$this->cacheTtl = $ttl;
	}

	/**
	 * Load the configured page, with caching.
	 * @param LinkTarget $configPage The page to load from
	 * @return array|StatusValue The content of the configuration page (as JSON
	 *   data in PHP-native format), or a StatusValue on error.
	 */
	public function load( LinkTarget $configPage ) {
		$cacheKey = $this->makeCacheKey( $configPage );
		$result = $this->cache->get( $cacheKey );

		if ( $result === false ) {
			$status = $this->fetchConfig( $configPage );
			if ( $status->isOK() ) {
				$result = $status->getValue();
				$cacheFlags = 0;
			} else {
				$result = $status;
				// FIXME use something more aggressive in production
				$cacheFlags = BagOStuff::WRITE_CACHE_ONLY;
			}
			$this->cache->set( $cacheKey, $result, $this->cacheTtl, $cacheFlags );
		}

		return $result;
	}

	/**
	 * Clear any cached contents from the given page.
	 * @param LinkTarget $configPage
	 */
	public function invalidate( LinkTarget $configPage ) {
		$cacheKey = $this->makeCacheKey( $configPage );
		$this->cache->delete( $cacheKey );
	}

	/**
	 * @param LinkTarget $configPage
	 * @return string
	 */
	private function makeCacheKey( LinkTarget $configPage ) {
		return $this->cache->makeKey( 'GrowthExperiments', 'NewcomerTasks',
			'config', $configPage->getNamespace(), $configPage->getDBkey() );
	}

	/**
	 * Fetch the contents of the configuration page, without caching.
	 * @param LinkTarget $configPage
	 * @return StatusValue Status object, with the configuration (as JSON data) on success.
	 */
	private function fetchConfig( LinkTarget $configPage ) {
		if ( $configPage->isExternal() ) {
			$url = Util::getRawUrl( $configPage, $this->titleFactory );
			return Util::getJsonUrl( $this->requestFactory, $url );
		} else {
			$revision = $this->revisionLookup->getRevisionByTitle( $configPage );
			if ( !$revision ) {
				return StatusValue::newFatal( new ApiRawMessage(
					'The configuration title does not exist.',
					'newcomer-tasks-configuration-loader-title-not-found'
				) );
			}
			$content = $revision->getContent( SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC );
			if ( !$content || !$content instanceof JsonContent ) {
				return StatusValue::newFatal( new ApiRawMessage(
					'The configuration title has no content or is not JSON content.',
					'newcomer-tasks-configuration-loader-content-error'
				) );
			}
			return FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
		}
	}

}
