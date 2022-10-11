'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	fs = require( 'fs' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	process = require( 'process' ),
	phpVersion = process.env.PHP_VERSION,
	phpFpmService = 'php' + phpVersion + '-fpm',
	// Take a snapshot of the local settings contents
	localSettings = fs.readFileSync( path.resolve( ip + '/LocalSettings.php' ) ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' ),
	Util = require( 'wdio-mediawiki/Util' );

const { SevereServiceError } = require( 'webdriverio' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	specFileRetries: 2,
	specFileRetriesDelay: 3,
	beforeSuite: async function () {
		await CreateAccountPage.createAccount( Util.getTestString( 'NewUser-' ), Util.getTestString() );
	},
	services: [ 'devtools', 'intercept' ],
	onPrepare: async function () {
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
			await childProcess.spawnSync(
				'service',
				[ phpFpmService, 'restart' ]
			);
			// Super ugly hack: Run this twice because sometimes the first invocation hangs.
			await childProcess.spawnSync(
				'service',
				[ phpFpmService, 'restart' ]
			);
		}
		// Import the test articles and their suggestions
		const suggestedEditsContentFilepath = path.resolve( __dirname + '/fixtures/SuggestedEditsContent.xml' );
		console.log( 'Importing ' + suggestedEditsContentFilepath );
		const importDumpResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/importDump.php', suggestedEditsContentFilepath ],
			{ cwd: ip }
		);
		if ( importDumpResult.status === 1 ) {
			console.log( String( importDumpResult.stderr ) );
			throw new SevereServiceError( 'Unable to import ' + suggestedEditsContentFilepath );
		}

		const newcomerTasksJsonFilepath = path.resolve( __dirname + '/fixtures/MediaWikiNewcomerTasks.json' );
		const newcomerTasksJson = fs.readFileSync( newcomerTasksJsonFilepath );
		console.log( 'Importing ' + newcomerTasksJsonFilepath + ' with content: ' + newcomerTasksJson );
		const newcomerTasksJsonResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/edit.php', '--user=Admin', 'MediaWiki:NewcomerTasks.json' ],
			{ input: newcomerTasksJson, cwd: ip }
		);
		if ( newcomerTasksJsonResult.status === 1 ) {
			console.log( String( newcomerTasksJsonResult.stderr ) );
			throw new SevereServiceError( 'Unable to import ' + newcomerTasksJsonFilepath );
		}
	},
	onComplete: async function () {
		// Remove the LocalSettings.php additions from onPrepare()
		await fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ), localSettings );
	}
};
