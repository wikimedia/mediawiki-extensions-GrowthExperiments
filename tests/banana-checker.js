'use strict';

const bananaChecker = require( 'grunt-banana-checker' );
const { MessagesDirs: { GrowthExperiments: i18nDirs } } = require( '../extension.json' );

let result = true;
for ( const i18nDir of i18nDirs ) {
	result = bananaChecker(
		i18nDir,
		{ requireLowerCase: 'off' },
		console.error,
		console.warn,
		true,
	) && result;
}
if ( !result ) {
	// eslint-disable-next-line n/no-process-exit
	process.exit( 1 );
}
