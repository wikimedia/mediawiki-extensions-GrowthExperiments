'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	fs = require( 'fs' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	// Take a snapshot of the local settings contents
	localSettings = fs.readFileSync( path.resolve( ip + '/LocalSettings.php' ) ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	Api = require( 'wdio-mediawiki/Api' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	//
	// Example:
	// logLevel: 'info',
	beforeSuite: function () {
		const username = Util.getTestString( 'NewUser-' );
		const password = Util.getTestString();
		browser.call( async () => {
			const bot = await Api.bot();
			await Api.createAccount( bot, username, password );
		} );
		UserLoginPage.login( username, password );
		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		browser.execute( async () => {
			return new mw.Api().saveOptions( {
				'growthexperiments-homepage-suggestededits-activated': 1,
				'growthexperiments-tour-homepage-discovery': 1,
				'growthexperiments-tour-homepage-welcome': 1
			} );
		} );
	},
	onPrepare: function () {
		fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ),
			// Load the service overrides
			localSettings + `
if ( file_exists( "$IP/extensions/GrowthExperiments/tests/selenium/fixtures/GrowthExperiments.LocalSettings.php" ) ) {
    require_once "$IP/extensions/GrowthExperiments/tests/selenium/fixtures/GrowthExperiments.LocalSettings.php";
}
` );
		// Import the test article and its suggestions
		childProcess.spawnSync(
			'php',
			[ 'maintenance/importDump.php', path.resolve( __dirname + '/fixtures/SuggestedEditsContent.xml' ) ],
			{ cwd: ip }
		);
		childProcess.spawnSync(
			'php',
			[ 'maintenance/edit.php', '--user=Admin', 'MediaWiki:NewcomerTasks.json' ],
			{ input: fs.readFileSync( path.resolve( __dirname + '/fixtures/MediaWiki:NewcomerTasks.json' ) ), cwd: ip }
		);
	},
	onComplete: function () {
		// Remove the LocalSettings.php additions from onPrepare()
		fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ), localSettings );
	}
};
