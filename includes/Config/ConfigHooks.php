<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\Specials\SpecialEditGrowthConfigRedirect;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

/**
 * Config related hooks. Used to keep alive the url to the legacy Special:EditGrowthConfig page,
 * the configuration form has been replaced by a CommunityConfiguration extension provider.
 *
 * The ultimate goal would be to remove the SpecialEditGrowthConfigRedirect once CommunityConfiguration
 * is well established and the traffic to Special:EditGrowthConfig is negligible.
 *
 * All handlers not explicitly implemented will be evaluated as no-ops.
 */
class ConfigHooks implements SpecialPage_initListHook {

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		$list['EditGrowthConfig'] = [
			'class' => SpecialEditGrowthConfigRedirect::class,
		];
	}
}
