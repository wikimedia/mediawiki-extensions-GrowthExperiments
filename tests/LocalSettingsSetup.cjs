'use strict';

const childProcess = require( 'child_process' ),
	process = require( 'process' ),
	phpVersion = process.env.PHP_VERSION,
	phpFpmService = 'php' + phpVersion + '-fpm';

/**
 * This is needed in Quibble + Apache (T225218) because we use supervisord to control
 * the php-fpm service, and with supervisord you need to restart the php-fpm service
 * in order to load updated php code.
 */
async function restartPhpFpmService() {
	if ( !process.env.QUIBBLE_APACHE ) {
		return;
	}
	console.log( 'Restarting ' + phpFpmService );
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'restart' ]
	);
	// Ugly hack: Run this twice because sometimes the first invocation hangs.
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'restart' ]
	);
}

module.exports = { restartPhpFpmService };
