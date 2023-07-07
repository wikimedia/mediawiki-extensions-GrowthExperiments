'use strict';
const vue = require( '@vitejs/plugin-vue' );
const { defineConfig } = require( 'vite' );

module.exports = exports = defineConfig( {
	plugins: [
		vue()
	],
	test: {
		environment: 'jsdom',
		globals: true,
		setupFiles: [ 'tests/setup.js' ],
		coverage: {
			include: [
				'components/**/*.vue'
			],
			all: true,
			lines: 78.1,
			functions: 63.8,
			branches: 91.1,
			statements: 78.1
		}
	}
} );
