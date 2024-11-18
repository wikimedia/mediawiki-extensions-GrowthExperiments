<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;

trait GrowthConfigLoaderStaticTrait {
	private static function getGrowthWikiConfig(): Config {
		return GrowthExperimentsServices::wrap(
			MediaWikiServices::getInstance()
		)->getGrowthWikiConfig();
	}

	private static function getGrowthConfig(): Config {
		return GrowthExperimentsServices::wrap(
			MediaWikiServices::getInstance()
		)->getGrowthConfig();
	}
}
