'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const process = require( 'process' );
const phpVersion = process.env.PHP_VERSION;
const phpFpmService = 'php' + phpVersion + '-fpm';
const childProcess = require( 'child_process' );
const ip = path.resolve( __dirname + '/../../../../' );
const localSettings = fs.readFileSync( path.resolve( ip + '/LocalSettings.php' ) );

exports.mochaGlobalSetup = async function () {
	console.log( 'Setting up modified LocalSettings.php' );
	fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ),
		// Load the service overrides
		localSettings + `
if ( file_exists( "$IP/extensions/GrowthExperiments/tests/selenium/fixtures/GrowthExperiments.LocalSettings.php" ) ) {
    require_once "$IP/extensions/GrowthExperiments/tests/selenium/fixtures/GrowthExperiments.LocalSettings.php";
}
` );
	// This is needed in Quibble + Apache (T225218) because we use supervisord to control
	// the php-fpm service, and with supervisord you need to restart the php-fpm service
	// in order to load updated php code.
	if ( process.env.QUIBBLE_APACHE ) {
		childProcess.spawnSync(
			'service',
			[ phpFpmService, 'restart' ]
		);
		// Super ugly hack: Run this twice because sometimes the first invocation hangs.
		childProcess.spawnSync(
			'service',
			[ phpFpmService, 'restart' ]
		);
	}
	// Import the test articles and their suggestions
	childProcess.spawnSync(
		'php',
		[ 'maintenance/importDump.php', path.resolve( __dirname + '/../selenium/fixtures/SuggestedEditsContent.xml' ) ],
		{ cwd: ip }
	);
	childProcess.spawnSync(
		'php',
		[ 'maintenance/edit.php', '--user=Admin', 'MediaWiki:NewcomerTasks.json' ],
		{ input: fs.readFileSync( path.resolve( __dirname + '/../selenium/fixtures/MediaWikiNewcomerTasks.json' ) ), cwd: ip }
	);
};

exports.mochaGlobalTeardown = async function () {
	console.log( 'Restoring LocalSettings.php' );
	fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ), localSettings );
};
