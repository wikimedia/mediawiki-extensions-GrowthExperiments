'use strict';
const path = require( 'path' );
const childProcess = require( 'child_process' );
const ip = path.resolve( __dirname + '/../../../../' );
const LocalSettingsSetup = require( __dirname + '/LocalSettingsSetup.cjs' );

exports.mochaGlobalSetup = async function () {
	await LocalSettingsSetup.overrideLocalSettings();
	await LocalSettingsSetup.restartPhpFpmService();
	// Import the test articles and their suggestions
	childProcess.spawnSync(
		'php',
		[ 'maintenance/run.php', 'importDump', path.resolve( __dirname + '/SuggestedEditsContent.xml' ) ],
		{ cwd: ip }
	);
};

exports.mochaGlobalTeardown = async function () {
	await LocalSettingsSetup.restoreLocalSettings();
	await LocalSettingsSetup.restartPhpFpmService();
};
