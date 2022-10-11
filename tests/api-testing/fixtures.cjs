'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const childProcess = require( 'child_process' );
const ip = path.resolve( __dirname + '/../../../../' );
const LocalSettingsSetup = require( __dirname + '/../LocalSettingsSetup.cjs' );
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
	await LocalSettingsSetup.restartPhpFpmService();
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
	await LocalSettingsSetup.restartPhpFpmService();
};
