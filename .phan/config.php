<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['minimum_target_php_version'] = '7.4';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/CentralAuth',
		'../../extensions/CirrusSearch',
		'../../extensions/CommunityConfiguration',
		'../../extensions/ConfirmEdit',
		'../../extensions/Echo',
		'../../extensions/EventBus',
		'../../extensions/EventLogging',
		'../../extensions/Flow',
		'../../extensions/MobileFrontend',
		'../../extensions/PageImages',
		'../../extensions/PageViewInfo',
		'../../extensions/Thanks',
		'../../extensions/TimedMediaHandler',
		'../../extensions/VisualEditor',
		'../../extensions/WikimediaMessages',
		'../../skins/MinervaNeue',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/CentralAuth',
		'../../extensions/CirrusSearch',
		'../../extensions/CommunityConfiguration',
		'../../extensions/ConfirmEdit',
		'../../extensions/Echo',
		'../../extensions/EventBus',
		'../../extensions/EventLogging',
		'../../extensions/Flow',
		'../../extensions/MobileFrontend',
		'../../extensions/PageImages',
		'../../extensions/PageViewInfo',
		'../../extensions/Thanks',
		'../../extensions/TimedMediaHandler',
		'../../extensions/VisualEditor',
		'../../extensions/WikimediaMessages',
		'../../skins/MinervaNeue',
	]
);

return $cfg;
