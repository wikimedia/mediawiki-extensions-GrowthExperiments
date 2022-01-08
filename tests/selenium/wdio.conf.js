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
	addConsoleLogs: true,
	beforeSuite: function () {
		const username = Util.getTestString( 'NewUser-' );
		const password = Util.getTestString();
		browser.call( async () => {
			const bot = await Api.bot();
			await Api.createAccount( bot, username, password );
		} );
		UserLoginPage.login( username, password );
		browser.executeAsync( ( done ) =>
			mw.loader.using( 'mediawiki.api' ).then( () =>
				new mw.Api().saveOptions( {
					'growthexperiments-homepage-suggestededits-activated': 1,
					'growthexperiments-tour-homepage-discovery': 1,
					'growthexperiments-tour-homepage-welcome': 1
				} ).done( () => done() )
			)
		);
		browser.executeAsync( ( done ) =>
			mw.loader.using( 'ext.growthExperiments.SuggestedEditSession' ).then( () =>
				ge.utils.setUserVariant( 'control' )
			).done( () => done() )
		);
	},
	services: [ 'devtools' ],
	onPrepare: function () {
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
		// TODO: Add a conditional so that this only executes when we're in the quibble + apache
		//   environment.
		childProcess.spawnSync(
			'service',
			[ 'php7.2-fpm', 'restart' ]
		);
		// Super ugly hack: Run this twice because sometimes the first invocation hangs.
		childProcess.spawnSync(
			'service',
			[ 'php7.2-fpm', 'restart' ]
		);
		// Import the test articles and their suggestions
		childProcess.spawnSync(
			'php',
			[ 'maintenance/importDump.php', path.resolve( __dirname + '/fixtures/SuggestedEditsContent.xml' ) ],
			{ cwd: ip }
		);
		childProcess.spawnSync(
			'php',
			[
				'extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/fixtures/Douglas_Adams.suggestions.json' ),
				'--title=Douglas_Adams'
			],
			{ cwd: ip }
		);
		childProcess.spawnSync(
			'php',
			[
				'extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/fixtures/The_Hitchhikers_Guide_to_the_Galaxy.suggestions.json' ),
				'--title=The_Hitchhiker\'s_Guide_to_the_Galaxy'
			],
			{ cwd: ip }
		);
		childProcess.spawnSync(
			'php',
			[ 'maintenance/edit.php', '--user=Admin', 'MediaWiki:NewcomerTasks.json' ],
			{ input: fs.readFileSync( path.resolve( __dirname + '/fixtures/MediaWikiNewcomerTasks.json' ) ), cwd: ip }
		);
	},
	onComplete: function () {
		// Remove the LocalSettings.php additions from onPrepare()
		fs.writeFileSync( path.resolve( ip + '/LocalSettings.php' ), localSettings );
	}
};
