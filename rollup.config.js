'use strict';
const resolve = require( '@rollup/plugin-node-resolve' );
const terser = require( '@rollup/plugin-terser' );

/**
 * Config file for generating a custom build of d3, containing only the packages
 * we need for the Impact module.
 */
module.exports = {
	input: 'modules/lib/d3/index.js',
	output: {
		file: 'modules/lib/d3/d3.min.js',
		format: 'umd',
		compact: false,
		name: 'd3',
	},
	plugins: [
		resolve(),
		terser(),
	],
	// upstream d3 rollup.config.js has this as well.
	onwarn( message, warn ) {
		if ( message.code === 'CIRCULAR_DEPENDENCY' ) {
			return;
		}
		warn( message );
	},
};
