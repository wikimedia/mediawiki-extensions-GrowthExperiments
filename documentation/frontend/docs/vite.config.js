const { defineConfig } = require( 'vite' );

module.exports = defineConfig( {
	server: {
		fs: {
			/**
			 * Allow serving files from outside project root.
			 * Accepts a path to specify the custom workspace root.
			 */
			allow: [ '../../..' ]
		}
	}
} );
