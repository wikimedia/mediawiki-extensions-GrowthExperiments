'use strict';
const LocalSettingsSetup = require( __dirname + '/LocalSettingsSetup.cjs' );

exports.mochaGlobalSetup = async function () {
	await LocalSettingsSetup.overrideLocalSettings();
	await LocalSettingsSetup.restartPhpFpmService();
};

exports.mochaGlobalTeardown = async function () {
	await LocalSettingsSetup.restoreLocalSettings();
	await LocalSettingsSetup.restartPhpFpmService();
};
