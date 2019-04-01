<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../skins/MinervaNeue',
		'../../extensions/PageImages',
		'../../extensions/PageViewInfo',
		'../../extensions/EventLogging',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../skins/MinervaNeue',
		'../../extensions/PageImages',
		'../../extensions/PageViewInfo',
		'../../extensions/EventLogging',
	]
);

return $cfg;
