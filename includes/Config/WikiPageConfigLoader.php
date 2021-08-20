<?php

namespace GrowthExperiments\Config;

use ApiRawMessage;
use BagOStuff;
use DBAccessObjectUtils;
use FormatJson;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Util;
use HashBagOStuff;
use IDBAccessObject;
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
class WikiPageConfigLoader implements IDBAccessObject, ICustomReadConstants {

	/** @var ConfigValidatorFactory */
	private $configValidatorFactory;

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
	 * @param ConfigValidatorFactory $configValidatorFactory
	 * @param HttpRequestFactory $requestFactory
	 * @param RevisionLookup $revisionLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ConfigValidatorFactory $configValidatorFactory,
		HttpRequestFactory $requestFactory,
		RevisionLookup $revisionLookup,
		TitleFactory $titleFactory
	) {
		$this->configValidatorFactory = $configValidatorFactory;
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
	 * @param int $flags bit field, see self::READ_XXX
	 * @return array|StatusValue The content of the configuration page (as JSON
	 *   data in PHP-native format), or a StatusValue on error.
	 */
	public function load( LinkTarget $configPage, int $flags = 0 ) {
		$cacheKey = $this->makeCacheKey( $configPage );

		if (
			!DBAccessObjectUtils::hasFlags( $flags, self::READ_LATEST ) &&
			// This is a custom flag, but bitfield logic should work regardless.
			!DBAccessObjectUtils::hasFlags( $flags, self::READ_UNCACHED )
		) {
			// Consult cache
			$result = $this->cache->get( $cacheKey );
		} else {
			// Pretend there is no cache entry and invalidate cache
			$result = false;
			$this->invalidate( $configPage );
		}

		if ( $result === false ) {
			// this also stores the value in cache
			$result = $this->loadUncached( $configPage, $flags );
		}

		return $result;
	}

	/**
	 *
	 * @param int $flags Bitfield consisting of READ_* constants
	 * @return int Bitfield consisting only of standard IDBAccessObject READ_* constants.
	 */
	private function removeCustomFlags( int $flags ): int {
		return $flags & ~self::READ_UNCACHED;
	}

	/**
	 * Load the configuration page, bypassing caching.
	 *
	 * This stores the loaded value to cache.
	 *
	 * @param LinkTarget $configPage
	 * @param int $flags
	 * @return false|mixed|StatusValue
	 */
	private function loadUncached( LinkTarget $configPage, int $flags = 0 ) {
		$cacheKey = $this->makeCacheKey( $configPage );

		$result = false;
		$status = $this->fetchConfig( $configPage, $this->removeCustomFlags( $flags ) );
		$cacheFlags = 0;
		if ( $status->isOK() ) {
			$result = $status->getValue();
			$status->merge(
				$this->configValidatorFactory
					->newConfigValidator( $configPage )
					->validate( $result )
			);
		}
		if ( !$status->isOK() ) {
			$result = $status;
			$cacheFlags = BagOStuff::WRITE_CACHE_ONLY;
		}

		$this->cache->set( $cacheKey, $result, $this->cacheTtl, $cacheFlags );
		return $result;
	}

	/**
	 * Fetch the contents of the configuration page, without caching.
	 * @param LinkTarget $configPage
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX; do NOT pass READ_UNCACHED
	 * @return StatusValue Status object, with the configuration (as JSON data) on success.
	 */
	private function fetchConfig( LinkTarget $configPage, int $flags ) {
		// TODO: Move newcomer-tasks-* messages to...somewhere more generic

		if ( $configPage->isExternal() ) {
			$url = Util::getRawUrl( $configPage, $this->titleFactory );
			return Util::getJsonUrl( $this->requestFactory, $url );
		} else {
			$revision = $this->revisionLookup->getRevisionByTitle( $configPage, 0, $flags );
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
