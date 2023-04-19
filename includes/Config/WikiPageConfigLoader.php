<?php

namespace GrowthExperiments\Config;

use ApiRawMessage;
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
use MediaWiki\Title\TitleFactory;
use StatusValue;
use WANObjectCache;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

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

	private ConfigValidatorFactory $configValidatorFactory;
	private HttpRequestFactory $requestFactory;
	private RevisionLookup $revisionLookup;
	private TitleFactory $titleFactory;
	private WANObjectCache $cache;
	private HashBagOStuff $inProcessCache;

	/**
	 * @param WANObjectCache $cache
	 * @param ConfigValidatorFactory $configValidatorFactory
	 * @param HttpRequestFactory $requestFactory
	 * @param RevisionLookup $revisionLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		ConfigValidatorFactory $configValidatorFactory,
		HttpRequestFactory $requestFactory,
		RevisionLookup $revisionLookup,
		TitleFactory $titleFactory
	) {
		$this->cache = $cache;
		$this->inProcessCache = new HashBagOStuff();
		$this->configValidatorFactory = $configValidatorFactory;
		$this->requestFactory = $requestFactory;
		$this->revisionLookup = $revisionLookup;
		$this->titleFactory = $titleFactory;
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
		$this->inProcessCache->delete( $cacheKey );
	}

	/**
	 * Load the configured page, with caching.
	 * @param LinkTarget $configPage
	 * @param int $flags bit field, see self::READ_XXX
	 * @return array|StatusValue The content of the configuration page (as JSON
	 *   data in PHP-native format), or a StatusValue on error.
	 */
	public function load( LinkTarget $configPage, int $flags = 0 ) {
		if (
			DBAccessObjectUtils::hasFlags( $flags, self::READ_LATEST ) ||
			// This is a custom flag, but bitfield logic should work regardless.
			DBAccessObjectUtils::hasFlags( $flags, self::READ_UNCACHED )
		) {
			// User does not want to used cached data, invalidate the cache.
			$this->invalidate( $configPage );
		}

		// WANObjectCache has an in-process cache (pcTTL), but it is not subject
		// to invalidation, which breaks WikiPageConfigLoaderTest.
		return $this->inProcessCache->getWithSetCallback(
			$this->makeCacheKey( $configPage ),
			ExpirationAwareness::TTL_INDEFINITE,
			function () use ( $configPage, $flags ) {
				return $this->loadFromWanCache( $configPage, $flags );
			}
		);
	}

	/**
	 * Load configuration from the WAN cache
	 *
	 * @param LinkTarget $configPage
	 * @param int $flags bit field, see self::READ_XXX
	 * @return array|StatusValue The content of the configuration page (as JSON
	 *   data in PHP-native format), or a StatusValue on error.
	 */
	private function loadFromWanCache( LinkTarget $configPage, int $flags = 0 ) {
		return $this->cache->getWithSetCallback(
			$this->makeCacheKey( $configPage ),
			// Cache config for a day; cache is invalidated by ConfigHooks::onPageSaveComplete
			// and WikiPageConfigWriter::save when config files are changed.,
			ExpirationAwareness::TTL_DAY,
			function ( $oldValue, &$ttl ) use ( $configPage, $flags ) {
				$result = $this->loadUncached( $configPage, $flags );
				if ( $result instanceof StatusValue ) {
					// error should not be cached
					$ttl = ExpirationAwareness::TTL_UNCACHEABLE;
				}
				return $result;
			}
		);
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
	 * Caller is responsible for caching the result if desired.
	 *
	 * @param LinkTarget $configPage
	 * @param int $flags
	 * @return array|StatusValue
	 */
	private function loadUncached( LinkTarget $configPage, int $flags = 0 ) {
		$result = false;
		$status = $this->fetchConfig( $configPage, $this->removeCustomFlags( $flags ) );
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
		}

		return $result;
	}

	/**
	 * Fetch the contents of the configuration page, without caching.
	 *
	 * Result is not validated with a config validator.
	 *
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
				// The configuration page does not exist. Pretend it does not configure anything
				// specific (failure mode and empty-page behavior is equal, see T325236).
				return StatusValue::newGood( $this->configValidatorFactory
					->newConfigValidator( $configPage )
					->getDefaultContent()
				);
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
