( function () {

	var Utils = require( '../utils/ext.growthExperiments.Utils.js' );

	/**
	 * @class mw.libs.ge.HelpPanelLogger
	 * @constructor
	 * @param {boolean} enabled
	 * @param {Object} [config]
	 * @cfg string [editorInterface] Allow overriding the editor_interface field for all events
	 * @cfg string [sessionId] Allow overriding the help_panel_session_id field for all events
	 */
	function HelpPanelLogger( enabled, config ) {
		config = config || {};
		this.enabled = enabled;
		this.userEditCount = mw.config.get( 'wgUserEditCount' );
		this.isMobile = OO.ui.isMobile();
		this.previousEditorInterface = '';
		this.editorInterface = config.editorInterface;
		this.helpPanelSessionId = config.sessionId || mw.user.generateRandomSessionId();
	}

	HelpPanelLogger.prototype.log = function ( action, data, metadataOverride ) {
		var eventData;
		if ( !this.enabled ) {
			return;
		}

		eventData = $.extend(
			{
				action: action,
				/* eslint-disable-next-line camelcase */
				action_data: Utils.serializeActionData( data )
			},
			this.getMetaData(),
			metadataOverride
		);

		// Test/debug using: `mw.trackSubscribe( 'event.HelpPanel', console.log );`
		mw.track(
			'event.HelpPanel',
			eventData
		);

		this.previousEditorInterface = eventData.editor_interface;
	};

	HelpPanelLogger.prototype.getMetaData = function () {
		var editor = this.getEditor(),
			readingMode = editor === 'reading';
		/* eslint-disable camelcase */
		return {
			user_id: mw.user.getId(),
			user_editcount: this.userEditCount,
			editor_interface: editor,
			is_mobile: this.isMobile,
			page_id: readingMode ? 0 : mw.config.get( 'wgArticleId' ),
			page_title: readingMode ? '' : mw.config.get( 'wgRelevantPageName' ),
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
			'reading',
			'homepage_help',
			'homepage_mentorship',
			'other'
		].indexOf( editor ) >= 0;
	};

	HelpPanelLogger.prototype.getEditor = function () {
		var veTarget,
			surface,
			mode,
			uri = new mw.Uri();

		if ( this.editorInterface ) {
			return this.editorInterface;
		}

		if ( this.isMobile ) {
			if ( !uri.fragment || !uri.fragment.match( /\/editor\/\d/ ) ) {
				return 'reading';
			}

			// Mobile: wikitext
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( 'textarea#wikitext-editor:visible' ).length ) {
				return 'wikitext';
			}

			// Mobile: VE
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( '.ve-init-mw-mobileArticleTarget:visible' ).length ) {
				return 'visualeditor';
			}

			// If we haven't found a textarea or VE target and we're not in reading mode,
			// then the current editor will be the same as the previous interface.
			return this.previousEditorInterface;
		} else {
			// Desktop: VE in visual or source mode
			veTarget = OO.getProp( window, 've', 'init', 'target' );
			if ( veTarget ) {
				surface = veTarget.getSurface();
				if ( surface ) {
					mode = surface.getMode();
					if ( mode === 'source' ) {
						return 'wikitext-2017';
					}

					if ( mode === 'visual' ) {
						return 'visualeditor';
					}
				}
			}

			// Desktop: old wikitext editor
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( '#wpTextbox1:visible' ).length ) {
				return 'wikitext';
			}

			if ( ( !uri.query.action || uri.query.action === 'view' ) && !uri.query.veaction ) {
				return 'reading';
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

	module.exports = HelpPanelLogger;

}() );
