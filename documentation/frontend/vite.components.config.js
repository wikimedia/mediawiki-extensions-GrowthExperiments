'use strict';
const vue = require( '@vitejs/plugin-vue' );
const { resolve } = require( 'path' );
const { defineConfig } = require( 'vite' );

module.exports = exports = defineConfig( {
	plugins: [
		vue()
	],
	build: {
		lib: {
			// Could also be a dictionary or array of multiple entry points
			entry: resolve( __dirname, './component-demos/lib.js' ),
			name: 'ext.growthExperiments.prototypes',
			// the proper extensions will be added
			fileName: 'growthexperiments-prototypes',
			formats: [ 'umd' ]
		},
		rollupOptions: {
			// make sure to externalize deps that shouldn't be bundled
			// into your library, so far: pinia, vue, @wikimedia/codex
			external: [
				'pinia',
				'vue',
				'@wikimedia/codex'
			],
			output: {
				// Provide global variables to use in the UMD build
				// for externalized deps
				globals: {
					vue: 'Vue'
				}
			}
		}
	}
} );
