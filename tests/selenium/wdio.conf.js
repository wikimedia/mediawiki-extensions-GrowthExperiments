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
	specFileRetries: 2,
	specFileRetriesDelay: 3,
	addConsoleLogs: true,
	beforeSuite: async function () {
		const username = Util.getTestString( 'NewUser-' );
		const password = Util.getTestString();
		await browser.call( async () => {
			const bot = await Api.bot();
			await Api.createAccount( bot, username, password );
		} );
		await UserLoginPage.login( username, password );
		await browser.execute( ( done ) =>
			mw.loader.using( 'mediawiki.api' ).then( () =>
				new mw.Api().saveOptions( {
					'growthexperiments-homepage-suggestededits-activated': 1,
					'growthexperiments-tour-homepage-discovery': 1,
					'growthexperiments-tour-homepage-welcome': 1
				} ).done( () => done() )
			)
		);
		await browser.execute( ( done ) =>
			mw.loader.using( 'ext.growthExperiments.SuggestedEditSession' ).then( () =>
				ge.utils.setUserVariant( 'control' )
			).done( () => done() )
		);
	},
	services: [ 'devtools' ],
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
				[ 'php7.2-fpm', 'restart' ]
			);
			// Super ugly hack: Run this twice because sometimes the first invocation hangs.
			await childProcess.spawnSync(
				'service',
				[ 'php7.2-fpm', 'restart' ]
			);
		}
		// Import the test articles and their suggestions
		await childProcess.spawnSync(
			'php',
			[ 'maintenance/importDump.php', path.resolve( __dirname + '/fixtures/SuggestedEditsContent.xml' ) ],
			{ cwd: ip }
		);
		await childProcess.spawnSync(
			'php',
			[
				'extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/fixtures/Douglas_Adams.suggestions.json' ),
				'--title=Douglas_Adams'
			],
			{ cwd: ip }
		);
		await childProcess.spawnSync(
			'php',
			[
				'extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/fixtures/The_Hitchhikers_Guide_to_the_Galaxy.suggestions.json' ),
				'--title=The_Hitchhiker\'s_Guide_to_the_Galaxy'
			],
			{ cwd: ip }
		);
		await childProcess.spawnSync(
			'php',
			[ 'maintenance/edit.php', '--user=Admin', 'MediaWiki:NewcomerTasks.json' ],
			{ input: fs.readFileSync( path.resolve( __dirname + '/fixtures/MediaWikiNewcomerTasks.json' ) ), cwd: ip }
		);
	},
	onComplete: async function () {
		// Remove the LocalSettings.php additions from onPrepare()
		await fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ), localSettings );
	}
};
