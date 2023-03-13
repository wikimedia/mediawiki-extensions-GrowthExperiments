'use strict';
const vue = require( '@vitejs/plugin-vue' );
const { defineConfig } = require( 'vite' );

module.exports = exports = defineConfig( {
	plugins: [
		vue()
	],
	test: {
		environment: 'jsdom'
	}
} );
