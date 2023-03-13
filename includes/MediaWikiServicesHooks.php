<?php

namespace GrowthExperiments;

use MediaWiki\Hook\MediaWikiServicesHook;

/**
 * Hook handler for MediaWikiServicesHook, used for changing configuration variables.
 * This hook cannot have any dependencies and must rely on globals entirely.
 */
class MediaWikiServicesHooks implements MediaWikiServicesHook {

	/** @inheritDoc */
	public function onMediaWikiServices( $services ) {
		global $wgNotifyTypeAvailabilityByCategory;
		$wgNotifyTypeAvailabilityByCategory['ge-newcomer']['push'] = false;
	}

}
