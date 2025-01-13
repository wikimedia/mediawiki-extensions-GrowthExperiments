<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Util;
use MediaWiki\MediaWikiServices;

/**
 * Placeholder hook implementation; it doesn't appear to be possible to register a hook handler
 * conditionally, so this is an empty implementation for cases when the hooks are sufficiently
 * handled within the CommunityConfiguration extension.
 */
class ConfigHooks {

	public function __call( $name, $arguments ) {
		// Intentionally empty; taken over by the CommunityConfiguration extension
	}

	/**
	 * Construct an appropriate ConfigHooks implementation
	 *
	 * @return LegacyConfigHooks|self
	 */
	public static function newHandler() {
		if ( Util::useCommunityConfiguration() ) {
			return new self();
		} else {
			$services = MediaWikiServices::getInstance();
			$geServices = GrowthExperimentsServices::wrap( $services );
			return new LegacyConfigHooks(
				$geServices->getWikiPageConfigValidatorFactory(),
				$geServices->getWikiPageConfigLoader(),
				$services->getTitleFactory(),
				$services->getMainConfig()
			);
		}
	}
}
