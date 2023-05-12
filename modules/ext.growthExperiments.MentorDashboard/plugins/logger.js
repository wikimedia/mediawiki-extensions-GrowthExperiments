/**
 * Vue plugin to make $log() available on any component.
 *
 * This is just a wrapper around MentorDashboardLogger.log method.
 *
 * @see modules/ext.growthExperiments.MentorDashboard/logger/Logger.js
 */

module.exports = exports = {
	install: ( app, { module, pageviewToken } ) => {
		const MentorDashboardLogger = require( '../logger/Logger.js' );

		app.provide( '$log', ( action, extraData ) => {
			const logger = new MentorDashboardLogger( pageviewToken );
			logger.log( module, action, extraData || {} );
		} );
	}
};
