<?php

namespace GrowthExperiments\Config;

use ApiRawMessage;
use BagOStuff;
use FormatJson;
use GrowthExperiments\Util;
use HashBagOStuff;
use JsonContent;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use StatusValue;
use TitleFactory;

/**
 * This class allows callers to fetch various variables
 * from JSON pages stored on-wiki (the pages need to have JSON
 * as their content model). It is currently used for configuration
 * of NewcomerTasks (see [[:w:cs:MediaWiki:NewcomerTasks]] as an example).
 *
 * The MediaWiki pages need to be formatted like this:
 * {
 * 		"ConfigVariable": "value",
 * 		"OtherConfigVariable": "value"
 * }
 *
 * Previously present in GrowthExperiments
 * as \GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageLoader,
 * generalized to this class taking care about config in general.
 */
class WikiPageConfigLoader {
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

		$this->setCache( new HashBagOStuff(), 0 );
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
	 * @param LinkTarget $configPage
	 * @return string
	 */
	private function makeCacheKey( LinkTarget $configPage ) {
		return $this->cache->makeKey( 'GrowthExperiments',
			'config', $configPage->getNamespace(), $configPage->getDBkey() );
	}

	/**
	 * @param LinkTarget $configPage
	 */
	public function invalidate( LinkTarget $configPage ) {
		$cacheKey = $this->makeCacheKey( $configPage );
		$this->cache->delete( $cacheKey );
	}

	/**
	 * Load the configured page, with caching.
	 * @param LinkTarget $configPage
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
	 * Fetch the contents of the configuration page, without caching.
	 * @param LinkTarget $configPage
	 * @return StatusValue Status object, with the configuration (as JSON data) on success.
	 */
	private function fetchConfig( LinkTarget $configPage ) {
		// TODO: Move newcomer-tasks-* messages to...somewhere more generic

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
