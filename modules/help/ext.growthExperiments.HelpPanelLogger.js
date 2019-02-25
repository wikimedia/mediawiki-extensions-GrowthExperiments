( function () {

	/**
	 * @class mw.libs.ge.HelpPanelLogger
	 * @constructor
	 * @param {boolean} enabled
	 */
	function HelpPanelLogger( enabled ) {
		this.enabled = enabled;
		this.readingMode = mw.config.get( 'wgAction' ) === 'view';
		this.userEditCount = mw.config.get( 'wgUserEditCount' );
		this.clearSessionId();
		this.logged = {};
	}

	HelpPanelLogger.prototype.clearSessionId = function () {
		this.helpPanelSessionId = mw.user.generateRandomSessionId();
	};

	HelpPanelLogger.prototype.log = function ( action, data, metadataOverride ) {
		if ( !this.enabled ) {
			return;
		}

		// Test/debug using: `mw.trackSubscribe( 'event.HelpPanel', console.log );`
		mw.track(
			'event.HelpPanel',
			$.extend(
				{
					action: action,
					/* eslint-disable-next-line camelcase */
					action_data: this.serializeActionData( data )
				},
				this.getMetaData(),
				metadataOverride
			)
		);

		this.logged[ action ] = true;
	};

	HelpPanelLogger.prototype.logOnce = function ( action, data, metadataOverride ) {
		if ( !this.enabled || this.logged[ action ] ) {
			return;
		}

		this.log( action, data, metadataOverride );
	};

	HelpPanelLogger.prototype.serializeActionData = function ( data ) {
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

	HelpPanelLogger.prototype.getMetaData = function () {
		/* eslint-disable camelcase */
		return {
			user_id: mw.user.getId(),
			user_editcount: this.userEditCount,
			editor_interface: this.getEditor(),
			is_mobile: OO.ui.isMobile(),
			page_id: this.readingMode ? 0 : mw.config.get( 'wgArticleId' ),
			page_title: this.readingMode ? '' : mw.config.get( 'wgRelevantPageName' ),
			page_ns: mw.config.get( 'wgNamespaceNumber' ),
			user_can_edit: mw.config.get( 'wgIsProbablyEditable' ),
			page_protection: this.getPageRestrictions(),
			session_token: mw.user.sessionId(),
			help_panel_session_id: this.helpPanelSessionId
		};
		/* eslint-enable camelcase */
	};

	HelpPanelLogger.prototype.isValidEditor = function ( editor ) {
		return [
			'wikitext',
			'wikitext-2017',
			'visualeditor',
			'other'
		].indexOf( editor ) >= 0;
	};

	HelpPanelLogger.prototype.getEditor = function () {
		var veTarget,
			mode;

		if ( this.readingMode ) {
			return 'reading';
		}

		// Desktop: old wikitext editor
		if ( $( '#wpTextbox1:visible' ).length ) {
			return 'wikitext';
		}

		// Desktop: VE in visual or source mode
		veTarget = OO.getProp( window, 've', 'init', 'target' );
		if ( veTarget ) {
			mode = veTarget.getSurface().getMode();
			if ( mode === 'source' ) {
				return 'wikitext-2017';
			}

			if ( mode === 'visual' ) {
				return 'visualeditor';
			}
		}

		// Mobile: wikitext
		if ( $( 'textarea#wikitext-editor:visible' ).length ) {
			return 'wikitext';
		}

		// Mobile: VE
		if ( $( '.ve-init-mw-mobileArticleTarget:visible' ).length ) {
			return 'visualeditor';
		}

		return 'other';
	};

	HelpPanelLogger.prototype.getPageRestrictions = function () {
		// wgRestrictionCreate, wgRestrictionEdit, wgRestrictionMove
		return [ 'create', 'edit', 'move' ]
			.map( function ( action ) {
				var restrictions = mw.config.get(
					'wgRestriction' +
					action[ 0 ].toUpperCase() +
					action.substr( 1 ).toLowerCase()
				);
				if ( restrictions && restrictions.length ) {
					return action + '=' + restrictions.join( ',' );
				}
			} )
			.filter( function ( r ) {
				return r;
			} )
			.join( ';' );
	};

	HelpPanelLogger.prototype.incrementUserEditCount = function () {
		this.userEditCount++;
	};

	module.exports = HelpPanelLogger;

}() );
