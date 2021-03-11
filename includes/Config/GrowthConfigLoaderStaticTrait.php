<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\MediaWikiServices;

trait GrowthConfigLoaderStaticTrait {
	private static function getGrowthWikiConfig() {
		return GrowthExperimentsServices::wrap(
			MediaWikiServices::getInstance()
		)->getGrowthWikiConfig();
	}

	private static function getGrowthConfig() {
		return GrowthExperimentsServices::wrap(
			MediaWikiServices::getInstance()
		)->getGrowthConfig();
	}
}
