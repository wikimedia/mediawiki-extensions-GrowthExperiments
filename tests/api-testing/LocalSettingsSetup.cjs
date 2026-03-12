'use strict';

const childProcess = require( 'child_process' ),
	process = require( 'process' ),
	phpVersion = process.env.PHP_VERSION,
	phpFpmService = 'php' + phpVersion + '-fpm',
	fs = require( 'fs' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	localSettingsPath = path.resolve( ip + '/LocalSettings.php' ),
	localSettingsContents = fs.readFileSync( localSettingsPath );

/**
 * Reset the PHP-Fpm opcache under Quibble environment
 *
 * In the CI environment, PHP Fpm is never revalidating files once they have
 * entered the opcache (`opcache.validate_timestamps=0`).
 *
 * The first request hitting MediaWiki triggers caching of `LocalSettings.php`
 * and subsequent changes to it (via `overrideLocalSettings()` will thus not
 * been taken in account since PHP serves it from the stalled cache (as
 * intended).
 *
 * The issue notably happens when running tests in parallel, some test suites
 * might not alter the `LocalSettings.php`, the stock one is thus cached and
 * when another tests changes the file, the new settings are now taken in
 * account on any test relying on the change ends up failing.
 *
 * We hit that case for the API Testing suite, will most probably hit it when
 * if Cypress tests are made to run in parallel and that case for Webdriver.io:
 * https://phabricator.wikimedia.org/T276428#7194025
 *
 * The PHP-Fpm opcache is held in shared memory and we thus can not clear it
 * using `opcache_reset()` from the PHP CLI. However the opcache is cleared
 * when reloading PHP-Fpm https://phabricator.wikimedia.org/T418369#11703410
 */
async function resetPhpFpmOpCache() {
	if ( !process.env.QUIBBLE_APACHE ) {
		return;
	}
	console.log( 'Clearing opcache by reloading ' + phpFpmService );
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'reload' ]
	);
}

/**
 * Require the GrowthExperiments.LocalSettings.php in the main LocalSettings.php.
 *
 * Note that you need to call resetPhpFpmOpCache() for this to take effect in
 * a Quibble environment.
 */
async function overrideLocalSettings() {
	console.log( 'Setting up modified ' + localSettingsPath );
	fs.writeFileSync( localSettingsPath,
		localSettingsContents + `
if ( file_exists( "$wgExtensionDirectory/GrowthExperiments/tests/api-testing/GrowthExperiments.LocalSettings.php" ) ) {
	require_once "$wgExtensionDirectory/GrowthExperiments/tests/api-testing/GrowthExperiments.LocalSettings.php";
}
` );
}
/**
 * Restore the original, unmodified LocalSettings.php.
 *
 * Note that you need to call resetPhpFpmOpCache() for this to take effect in
 * a Quibble environment.
 */
async function restoreLocalSettings() {
	console.log( 'Restoring original ' + localSettingsPath );
	await fs.writeFileSync( localSettingsPath, localSettingsContents );
}

module.exports = { resetPhpFpmOpCache, overrideLocalSettings, restoreLocalSettings };
