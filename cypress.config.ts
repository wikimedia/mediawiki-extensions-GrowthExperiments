import { defineConfig } from 'cypress';
// eslint-disable-next-line n/no-missing-import
import { mwApiCommands } from './cypress/support/MwApiPlugin';
// eslint-disable-next-line n/no-missing-import
import LocalSettingsSetup from './cypress/support/LocalSettingsSetup';
// eslint-disable-next-line @typescript-eslint/no-require-imports
const installCypressLogsPrinter = require( 'cypress-terminal-report/src/installLogsPrinter' );
import * as childProcess from 'child_process';

const envLogDir = process.env.LOG_DIR ? process.env.LOG_DIR + '/GrowthExperiments' : null;

if ( process.env.MW_SERVER === undefined || process.env.MW_SCRIPT_PATH === undefined ||
     process.env.MEDIAWIKI_USER === undefined || process.env.MEDIAWIKI_PASSWORD === undefined ) {
	throw new Error( 'Please define MW_SERVER, MW_SCRIPT_PATH, ' +
		'MEDIAWIKI_USER and MEDIAWIKI_PASSWORD environment variables' );
}
process.env.REST_BASE_URL = process.env.MW_SERVER + process.env.MW_SCRIPT_PATH + '/';

export default defineConfig( {
	e2e: {
		supportFile: 'cypress/support/e2e.ts',
		baseUrl: process.env.MW_SERVER + process.env.MW_SCRIPT_PATH,
		env: {
			mediawikiAdminUsername: process.env.MEDIAWIKI_USER,
			mediawikiAdminPassword: process.env.MEDIAWIKI_PASSWORD,
		},
		setupNodeEvents( on, config ) {
			installCypressLogsPrinter( on );
			on( 'task', {
				...mwApiCommands( config ),
			} );
			on( 'before:run', async () => {
				LocalSettingsSetup.overrideLocalSettings();
				await LocalSettingsSetup.restartPhpFpmService();

				const ip = process.env.MW_INSTALL_PATH || '../..';
				const command = process.env.MW_MAINTENANCE_COMMAND || 'php';
				const maintScriptRunnerArgs = process.env.MW_MAINTENANCE_ARGS ? process.env.MW_MAINTENANCE_ARGS.split( ' ' ) : [ 'maintenance/run.php' ];
				const maintScriptResult = childProcess.spawnSync(
					command,
					[
						...maintScriptRunnerArgs,
						'GrowthExperiments:PrepareBrowserTests',
					],
					{ cwd: ip },
				);
				console.log( 'stdout:' );
				console.log( String( maintScriptResult.stdout ) );
				console.log( 'stderr:' );
				console.log( String( maintScriptResult.stderr ) );
				if ( maintScriptResult.status !== 0 ) {
					throw new Error( 'unable to run the maintenance script setting up the browser tests' );
				}
			} );
			on( 'after:run', async () => {
				LocalSettingsSetup.restoreLocalSettings();
				await LocalSettingsSetup.restartPhpFpmService();
			} );
		},
		defaultCommandTimeout: 20000,
	},
	screenshotsFolder: envLogDir || 'cypress/screenshots',
	videosFolder: envLogDir || 'cypress/videos',
	video: true,
	downloadsFolder: envLogDir || 'cypress/downloads',
} );
