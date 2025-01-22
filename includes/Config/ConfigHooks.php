<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialEditGrowthConfigRedirect;
use GrowthExperiments\Util;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

/**
 * ConfigHooks implementation to be used when CommunityConfiguration extension is enabled.
 *
 * It doesn't appear to be possible to register a hook handler conditionally, so this is an empty
 * implementation for cases when the hooks are sufficiently handled within the
 * CommunityConfiguration extension.
 *
 * All handlers not explicitly implemented will be evaluated as no-ops.
 */
class ConfigHooks implements SpecialPage_initListHook {

	public function __call( $name, $arguments ) {
		// Intentionally empty; taken over by the CommunityConfiguration extension
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		// Only runs when CommunityConfiguration extension is used, no need to recheck that.
		$list['EditGrowthConfig'] = [
			'class' => SpecialEditGrowthConfigRedirect::class,
		];
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
