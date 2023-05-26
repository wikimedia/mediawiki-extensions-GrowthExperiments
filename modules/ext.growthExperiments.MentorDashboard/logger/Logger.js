/**
 * Logger for mediawiki/mentor_dashboard/interaction schema
 */

const Utils = require( '../../utils/Utils.js' );

/**
 * @param {string} pageviewToken
 * @class mw.libs.ge.MentorDashboardLogger
 * @constructor
 */
function MentorDashboardLogger( pageviewToken ) {
	this.schema = '/analytics/mediawiki/mentor_dashboard/interaction/1.0.0';
	this.stream = 'mediawiki.mentor_dashboard.interaction';
	this.userId = mw.user.getId();
	this.isMobile = mw.config.get( 'homepagemobile' ) || false;
	this.pageviewToken = pageviewToken;
}

MentorDashboardLogger.prototype.log = function ( module, action, extraData ) {
	const event = {
		/* eslint-disable camelcase */
		database: mw.config.get( 'wgDBname' ),
		action: action,
		action_data: Utils.serializeActionData( extraData ),
		module: module,
		is_mobile: this.isMobile,
		user_id: this.userId,
		pageview_token: this.pageviewToken
		/* eslint-enable camelcase */
	};
	event.$schema = this.schema;
	mw.eventLog.submit( this.stream, event );
};

module.exports = MentorDashboardLogger;
