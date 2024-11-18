'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	fs = require( 'fs' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	LocalSettingsSetup = require( __dirname + '/../LocalSettingsSetup.cjs' );

const { SevereServiceError } = require( 'webdriverio' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	maxInstances: 1,
	specFileRetries: 2,
	specFileRetriesDelay: 3,
	beforeSuite: async function () {
		await CreateAccountPage.createAccount( Util.getTestString( 'NewUser-' ), Util.getTestString() );
	},
	services: [ 'devtools', 'intercept' ],
	before: function ( capabilities, specs, browser ) {
		browser.log = function ( message, ...otherMessages ) {
			console.log( `${ Date.now() - browser.config.startOfTestTime }: ${ message }`, ...otherMessages );
		};

		browser.clickTillItGoesAway = function ( clickTarget, timeoutMsg ) {
			return browser.waitUntil(
				async () => {
					const awaitedTarget = await clickTarget;
					browser.log( 'Checking if ' + awaitedTarget.selector + ' is still existing' );
					if ( await clickTarget.isExisting() ) {
						browser.log( awaitedTarget.selector + ' still exists. Clicking it.' );
						await clickTarget.click();
						return false;
					}

					return true;
				},
				{
					timeout: 30000,
					interval: 500,
					timeoutMsg
				}
			);
		};
	},

	beforeTest: function ( test, context ) {
		config.beforeTest( test, context );
		browser.config.startOfTestTime = Date.now();
	},

	onPrepare: async function () {
		await LocalSettingsSetup.overrideLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
		// Import the test articles and their suggestions
		const suggestedEditsContentFilepath = path.resolve( __dirname + '/fixtures/SuggestedEditsContent.xml' );
		console.log( 'Importing ' + suggestedEditsContentFilepath );
		const importDumpResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/run.php', 'importDump', suggestedEditsContentFilepath ],
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
			[ 'maintenance/run.php', 'edit', '--user=Admin', 'MediaWiki:NewcomerTasks.json' ],
			{ input: newcomerTasksJson, cwd: ip }
		);
		if ( newcomerTasksJsonResult.status === 1 ) {
			console.log( String( newcomerTasksJsonResult.stderr ) );
			throw new SevereServiceError( 'Unable to import ' + newcomerTasksJsonFilepath );
		}

		console.log( 'Running jobs...' );
		const runJobsResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/run.php', 'runJobs' ],
			{ cwd: ip }
		);
		console.log( runJobsResult.stdout.toString( 'utf8' ) );
		console.log( 'Running update.php to clear caches' );
		const updatePhpResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/run.php', 'update', '--quick' ],
			{ cwd: ip }
		);
		console.log( updatePhpResult.stdout.toString( 'utf8' ) );
	},
	onComplete: async function () {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
