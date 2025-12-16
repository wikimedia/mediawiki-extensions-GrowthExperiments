<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

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
		'../../extensions/TestKitchen',
		'../../extensions/Thanks',
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
		'../../extensions/TestKitchen',
		'../../extensions/Thanks',
		'../../extensions/VisualEditor',
		'../../extensions/WikimediaMessages',
		'../../skins/MinervaNeue',
	]
);

return $cfg;
