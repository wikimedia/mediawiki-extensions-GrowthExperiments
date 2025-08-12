<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// TODO Fix these issues, suppressed to allow upgrading
$cfg['suppress_issue_types'][] = 'PhanUnusedPrivateMethodParameter';

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
		'../../extensions/MetricsPlatform',
		'../../extensions/MobileFrontend',
		'../../extensions/PageImages',
		'../../extensions/PageViewInfo',
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
		'../../extensions/MetricsPlatform',
		'../../extensions/MobileFrontend',
		'../../extensions/PageImages',
		'../../extensions/PageViewInfo',
		'../../extensions/Thanks',
		'../../extensions/VisualEditor',
		'../../extensions/WikimediaMessages',
		'../../skins/MinervaNeue',
	]
);

return $cfg;
