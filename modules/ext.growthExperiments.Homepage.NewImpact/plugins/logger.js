const Logger = require( '../../ext.growthExperiments.Homepage.Logger/index.js' );

/**
 * Vue plugin to make $log() available on any component.
 *
 * This is just a wrapper around HomepageModuleLogger.log method.
 *
 * @see modules/ext.growthExperiments.Homepage.Logger/index.js
 */
module.exports = exports = {
	install: ( app, { enabled, mode, pageviewToken } ) => {
		const logger = new Logger( enabled, pageviewToken );
		// provide configured $log() method to the application
		app.provide( '$log', ( module, action, extraData ) => {
			logger.log( module, mode, action, extraData );
		} );
	}
};
