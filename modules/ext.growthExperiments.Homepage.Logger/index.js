/**
 * Logger for HomepageModule EventLogging schema.
 *
 * The following files must be included together:
 * - ext.growthExperiments.Homepage.Logger/index.js
 * - utils/Utils.js
 * And the following modules:
 * - mediawiki.user (To avoid possible RL order issues in debug mode, not needed after resolving T225842)
 */
( function () {

	const Utils = require( '../utils/Utils.js' );

	/**
	 * @param {string} homepagePageviewToken
	 * @param {boolean} disabled
	 * @class mw.libs.ge.HomepageModuleLogger
	 * @constructor
	 */
	function HomepageModuleLogger( homepagePageviewToken, disabled = false ) {
		this.disabled = disabled;
		this.userId = mw.user.getId();
		this.userEditCount = mw.config.get( 'wgUserEditCount' ) || 0;
		this.isMobile = mw.config.get( 'homepagemobile' ) || false;
		this.homepagePageviewToken = homepagePageviewToken;
		this.exclusions = {
			start: [ 'impression' ]
		};
	}

	/**
	 * Log an event to the HomepageModule schema
	 *
	 * @param {string} module Name of the module
	 * @param {string} mode Rendering mode See constants in IDashboardModule.php
	 * @param {string} action User action
	 * @param {Object} [extraData] Additional data related to the action or the state of the module
	 */
	HomepageModuleLogger.prototype.log = function ( module, mode, action, extraData ) {
		if ( this.disabled ) {
			return;
		}
		if ( this.exclusions[ module ] && this.exclusions[ module ].includes( action ) ) {
			return;
		}

		const data = Object.assign(
			{},
			mw.config.get( 'wgGEHomepageModuleActionData-' + module ),
			extraData || {}
		);

		const event = {
			/* eslint-disable camelcase */
			action: action,
			action_data: Utils.serializeActionData( data ),
			user_id: this.userId,
			user_editcount: this.userEditCount,
			user_variant: Utils.getUserVariant(),
			module: module,
			is_mobile: this.isMobile,
			mode: mode,
			homepage_pageview_token: this.homepagePageviewToken
			/* eslint-enable camelcase */
		};
		const state = mw.config.get( 'wgGEHomepageModuleState-' + module );
		if ( state ) {
			// Don't pass things like event.state = '', that causes validation errors
			event.state = state;
		}
		mw.track( 'event.HomepageModule', event );
	};

	module.exports = HomepageModuleLogger;
}() );
