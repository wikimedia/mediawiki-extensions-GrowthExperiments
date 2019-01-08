( function () {

	function HelpPanelLogger( enabled ) {
		this.enabled = enabled;
		this.userEditCount = mw.config.get( 'wgUserEditCount' );
		this.clearSessionId();
	}

	HelpPanelLogger.prototype.clearSessionId = function () {
		this.helpPanelSessionId = mw.user.generateRandomSessionId();
	};

	HelpPanelLogger.prototype.log = function ( action, data ) {
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
				this.getMetaData()
			)
		);
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
			page_id: mw.config.get( 'wgArticleId' ),
			page_title: mw.config.get( 'wgRelevantPageName' ),
			page_ns: mw.config.get( 'wgNamespaceNumber' ),
			user_can_edit: mw.config.get( 'wgIsProbablyEditable' ),
			page_protection: this.getPageRestrictions(),
			session_token: mw.user.sessionId(),
			help_panel_session_id: this.helpPanelSessionId
		};
		/* eslint-enable camelcase */
	};

	HelpPanelLogger.prototype.getEditor = function () {
		var veTarget,
			mode;

		if ( $( '#wpTextbox1:visible' ).length ) {
			return 'wikitext';
		}

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

	OO.setProp( mw, 'libs', 'ge', 'HelpPanelLogger', HelpPanelLogger );

}() );
