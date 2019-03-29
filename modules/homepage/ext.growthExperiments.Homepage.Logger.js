( function () {

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
	}

	/**
	 * Log an event to the HomepageModule schema
	 *
	 * @param {string} module Name of the module
	 * @param {string} action User action
	 * @param {Object} [data] Additional data related to the action or the state of the module
	 */
	HomepageModuleLogger.prototype.log = function ( module, action, data ) {
		if ( !this.enabled ) {
			return;
		}

		mw.track( 'event.HomepageModule', {
			/* eslint-disable camelcase */
			action: action,
			action_data: this.serializeActionData( data ),
			user_id: this.userId,
			user_editcount: this.userEditCount,
			module: module,
			is_mobile: this.isMobile,
			homepage_pageview_token: this.homepagePageviewToken
			/* eslint-enable camelcase */
		} );
	};

	HomepageModuleLogger.prototype.serializeActionData = function ( data ) {
		if ( !data ) {
			return '';
		}

		if ( typeof data === 'object' ) {
			return Object.keys( data )
				.map( function ( key ) {
					return key + '=' + data[ key ];
				} )
				.join( ';' );
		}

		if ( Array.isArray( data ) ) {
			return data.join( ';' );
		}

		// assume it is string or number or bool
		return data;
	};

	module.exports = HomepageModuleLogger;
}() );
