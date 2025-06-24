import * as childProcess from 'child_process';
import * as process from 'process';
import * as fs from 'fs';
import * as path from 'path';

const phpVersion = process.env.PHP_VERSION;
const phpFpmService = 'php' + phpVersion + '-fpm';
const ip = process.env.MW_INSTALL_PATH || path.resolve( __dirname + '/../../../../' );
const localSettingsPath = ( process.env.LOCAL_SETTINGS_PATH || path.resolve( ip + '/LocalSettings.php' ) ).toString();
// eslint-disable-next-line security/detect-non-literal-fs-filename
const localSettingsContents = fs.readFileSync( localSettingsPath, 'utf-8' );

/**
 * This is needed in Quibble + Apache (T225218) because we use supervisord to control
 * the php-fpm service, and with supervisord you need to restart the php-fpm service
 * in order to load updated php code.
 */
async function restartPhpFpmService(): Promise<void> {
	if ( !process.env.QUIBBLE_APACHE ) {
		return;
	}
	console.log( 'Restarting ' + phpFpmService );
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'restart' ],
	);
	// Ugly hack: Run this twice because sometimes the first invocation hangs.
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'restart' ],
	);
}

/**
 * Require the GrowthExperiments.LocalSettings.php in the main LocalSettings.php. Note that you
 * need to call restartPhpFpmService for this take effect in a Quibble environment.
 *
 * @return {true}
 */
function overrideLocalSettings(): true {
	console.log( 'Setting up modified ' + localSettingsPath );
	// eslint-disable-next-line security/detect-non-literal-fs-filename
	fs.writeFileSync( localSettingsPath,
		localSettingsContents + `
// Cypress test code (is supposed to be removed after the test suite, safe to delete)
if ( file_exists( "$wgExtensionDirectory/GrowthExperiments/cypress/support/setupFixtures/GrowthExperiments.LocalSettings.php" ) ) {
	require_once "$wgExtensionDirectory/GrowthExperiments/cypress/support/setupFixtures/GrowthExperiments.LocalSettings.php";
}
` );
	return true;
}

/**
 * Restore the original, unmodified LocalSettings.php.
 *
 * Note that you need to call restartPhpFpmService for this to take effect in a
 * Quibble environment.
 *
 * @return {true}
 */
function restoreLocalSettings(): true {
	console.log( 'Restoring original ' + localSettingsPath );
	// eslint-disable-next-line security/detect-non-literal-fs-filename
	fs.writeFileSync( localSettingsPath, localSettingsContents );
	return true;
}

export default { restartPhpFpmService, overrideLocalSettings, restoreLocalSettings };
