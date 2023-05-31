/**
 * Plugin to facilitate the usage of existing loggers
 * in Vue components.
 *
 * The logger.log method will be available in component instances
 * as this.$log. The given logger can also be accessed from components
 * using inject( 'logger' ).
 */
module.exports = exports = {
	install: ( app, { mode, logger } ) => {
		// Make the logger available to application components thorugh inject
		app.provide( 'logger', logger );

		// Add configured $log() method to the application globalProperties
		// so it can be accessed from compponent instances
		app.config.globalProperties.$log = function ( module, action, extraData ) {
			logger.log( module, mode, action, extraData );
		};
	}
};
