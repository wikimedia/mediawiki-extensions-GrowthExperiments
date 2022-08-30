'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	fs = require( 'fs' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	// Take a snapshot of the local settings contents
	localSettings = fs.readFileSync( path.resolve( ip + '/LocalSettings.php' ) ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	LocalSettingsSetup = require( __dirname + '/../LocalSettingsSetup.cjs' );

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
		console.log( 'Require GrowthExperiments.LocalSettings.php...' );
		fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ),
			// Load the service overrides
			localSettings + `
if ( file_exists( "$IP/extensions/GrowthExperiments/tests/selenium/fixtures/GrowthExperiments.LocalSettings.php" ) ) {
	require_once "$IP/extensions/GrowthExperiments/tests/selenium/fixtures/GrowthExperiments.LocalSettings.php";
}
` );
		await LocalSettingsSetup.restartPhpFpmService();
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

		console.log( 'Running jobs...' );
		const runJobsResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/runJobs.php' ],
			{ cwd: ip }
		);
		console.log( runJobsResult.stdout.toString( 'utf8' ) );
		console.log( 'Running update.php to clear caches' );
		const updatePhpResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/update.php', '--quick' ],
			{ cwd: ip }
		);
		console.log( updatePhpResult.stdout.toString( 'utf8' ) );
	},
	onComplete: async function () {
		// Remove the LocalSettings.php additions from onPrepare()
		await fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ), localSettings );
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
