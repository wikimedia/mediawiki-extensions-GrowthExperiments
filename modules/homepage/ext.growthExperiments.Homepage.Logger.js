( function () {

	var Utils = require( '../utils/ext.growthExperiments.Utils.js' );

	/**
	 * @param {boolean} enabled
	 * @param {string} homepagePageviewToken
	 * @constructor
	 */
	function HomepageModuleLogger( enabled, homepagePageviewToken ) {
		this.enabled = enabled;
		this.userId = mw.user.getId();
		this.userEditCount = mw.config.get( 'wgUserEditCount' );
		this.isMobile = OO.ui.isMobile();
		this.homepagePageviewToken = homepagePageviewToken;
		this.modulesExcludedFromLogging = [ 'start' ];
	}

	/**
	 * Log an event to the HomepageModule schema
	 *
	 * @param {string} module Name of the module
	 * @param {string} action User action
	 * @param {string} state State of the module the user is interacting with.
	 * @param {Object} [data] Additional data related to the action or the state of the module
	 */
	HomepageModuleLogger.prototype.log = function ( module, action, state, data ) {
		if ( !this.enabled ) {
			return;
		}

		if ( this.modulesExcludedFromLogging.indexOf( module ) !== -1 ) {
			return;
		}

		mw.track( 'event.HomepageModule', {
			/* eslint-disable camelcase */
			action: action,
			action_data: Utils.serializeActionData( data ),
			user_id: this.userId,
			user_editcount: this.userEditCount,
			module: module,
			state: state,
			is_mobile: this.isMobile,
			homepage_pageview_token: this.homepagePageviewToken
			/* eslint-enable camelcase */
		} );
	};

	module.exports = HomepageModuleLogger;
}() );
