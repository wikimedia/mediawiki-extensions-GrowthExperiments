/**
 * Dependencies:
 * - mediawiki.user
 * - oojs-ui-core
 * - ./Utils.js
 */
( function () {

	const Utils = require( './Utils.js' );

	/**
	 * Logging helper for the HelpPanel EventLogging schema (analytics/legacy/helppanel).
	 * Originally written for the help panel, but now also used for a couple of unrelated
	 * things that also happen in articles: the post-edit dialog and the post-signup
	 * post-edit dialog.
	 *
	 * @class mw.libs.ge.HelpPanelLogger
	 * @see https://meta.wikimedia.org/wiki/Schema:HelpPanel
	 *
	 * @constructor
	 * @param {Object} [config]
	 * @param {string} [config.context] Allow overriding the context field for all events
	 * @param {string} [config.previousEditorInterface] Type of the last editor the user made an edit with,
	 *   if known. Used for editor_interface if the type of editor cannot be determined on the fly
	 *   (ie. we are not editing right now).
	 * @param {string} [config.isSuggestedTask] Allow overriding the is_suggested_task field for all events.
	 *   This must be set for suggested edits, the logger does not try to detect them.
	 * @param {string} [config.sessionId] Allow overriding the help_panel_session_id field for all events
	 */
	function HelpPanelLogger( config ) {
		config = config || {};
		// This will be updated via the setEditor method in response to
		// MobileFrontend's editorOpened/editorClosed hooks.
		this.editor = null;
		this.userEditCount = mw.config.get( 'wgUserEditCount' ) || 0;
		this.isMobile = OO.ui.isMobile();
		this.previousEditorInterface = config.previousEditorInterface || null;
		this.context = config.context || null;
		this.isSuggestedTask = config.isSuggestedTask || false;
		this.helpPanelSessionId = config.sessionId || mw.user.generateRandomSessionId();
	}

	/**
	 * Log a HelpPanel event.
	 *
	 * @param {string} action Value of the action field
	 * @param {Object|Array<string>|string|number|boolean} [data] Value of the action_data field
	 * @param {Object} [metadataOverride] An object with the values of any other fields. Those
	 *   fields are set to some default value if omitted.
	 */
	HelpPanelLogger.prototype.log = function ( action, data, metadataOverride ) {
		// T273700 in some rare cases the user is logged out when this is called, so to avoid
		// eventgate-validation issues make sure we have an ID.
		if ( !mw.user.getId() ) {
			return;
		}

		const eventData = Object.assign(
			{
				action: action,
				/* eslint-disable-next-line camelcase */
				action_data: Utils.serializeActionData( data )
			},
			this.getMetaData(),
			metadataOverride
		);

		mw.track( 'event.HelpPanel', eventData );

		this.previousEditorInterface = eventData.editor_interface;
	};

	HelpPanelLogger.prototype.getMetaData = function () {
		const defaultEditor = this.getEditor(),
			defaultContext = this.getContext(),
			editingModes = [ 'editing', 'postedit', 'postedit-nonsuggested' ],
			readingMode = ( !editingModes.includes( defaultContext ) );
		/* eslint-disable camelcase */
		return {
			user_id: mw.user.getId(),
			user_editcount: this.userEditCount,
			context: defaultContext,
			editor_interface: defaultEditor,
			is_suggested_task: this.isSuggestedTask,
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

	/**
	 * Check whether the user is editing right now.
	 *
	 * @private
	 * @return {boolean}
	 */
	HelpPanelLogger.prototype.isEditing = function () {
		const url = new URL( window.location.href );
		const searchParams = url.searchParams;

		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
			// Good enough for now; at some point special editing interfaces like ContentTranslate
			// might need special handling.
			return false;
		} else if ( this.isMobile ) {
			return Boolean( this.editor );
		} else {
			return Boolean( searchParams.get( 'veaction' ) ) ||
				searchParams.get( 'action' ) === 'edit' ||
				searchParams.get( 'action' ) === 'submit';
		}
	};

	/**
	 * Return what setting the help panel has been invoked in (editing a page or viewing a page;
	 * there are others but those are set via the metadata override mechanism).
	 *
	 * @return {string} A value appropriate for the context field of the schema.
	 */
	HelpPanelLogger.prototype.getContext = function () {
		return this.context || ( this.isEditing() ? 'editing' : 'reading' );
	};

	/**
	 * Get a value appropriate for the editor_interface field of the schema
	 * (the current editor, or the previously used editor, or a best guess what the editor
	 * would be).
	 *
	 * @return {string}
	 */
	HelpPanelLogger.prototype.getEditor = function () {
		if ( this.isEditing() ) {
			return this.getCurrentEditor();
		} else {
			return this.getPredictedEditor();
		}
	};

	/**
	 * MobileFrontend's editorOpened hook tells us which editor was opened.
	 *
	 * @param {string} editor
	 */
	HelpPanelLogger.prototype.setEditor = function ( editor ) {
		this.editor = editor;
		this.context = editor ? 'editing' : 'reading';
	};

	/**
	 * Returns the name of the current editor (in the format used by the editor_interface field
	 * of the schema). Should only be called when that editor is open.
	 *
	 * @private
	 * @return {string}
	 */
	HelpPanelLogger.prototype.getCurrentEditor = function () {

		// If the editor has already been set in response to a hook from
		// MobileFrontend, use that.
		// FIXME: We could use a similar approach for VE and wikipage.editform
		// hooks rather than making this an exception for mobile.
		if ( this.isMobile && this.editor ) {
			return this.editor;
		} else {
			// Desktop: VE in visual or source mode
			const veTarget = OO.getProp( window, 've', 'init', 'target' );
			if ( veTarget ) {
				const surface = veTarget.getSurface();
				if ( surface ) {
					const mode = surface.getMode();
					if ( mode === 'source' ) {
						return 'wikitext-2017';
					}

					if ( mode === 'visual' ) {
						return 'visualeditor';
					}
					if ( mode ) {
						return mode;
					}
				}
			}

			// Desktop: old wikitext editor
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( '#wpTextbox1:visible' ).length ||
				// wikitext editor with syntax highlighting
				// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
				$( '.wikiEditor-ui .CodeMirror:visible, .wikiEditor-ui .cm-editor:visible' ).length
			) {
				return 'wikitext';
			}
		}

		return 'other';
	};

	/**
	 * Try to guess what editor the user will use in their next editing session (in the format used
	 * by the editor_interface field of the schema).
	 *
	 * @private
	 * @return {string}
	 */
	HelpPanelLogger.prototype.getPredictedEditor = function () {
		// If we know what was used for the previous edit, return that.
		return this.previousEditorInterface || 'not-known';
	};

	HelpPanelLogger.prototype.getPageRestrictions = function () {
		// wgRestrictionCreate, wgRestrictionEdit, wgRestrictionMove
		return [ 'create', 'edit', 'move' ]
			.map( ( action ) => {
				const restrictions = mw.config.get(
					'wgRestriction' +
					action[ 0 ].toUpperCase() +
					action.slice( 1 ).toLowerCase()
				);
				if ( restrictions && restrictions.length ) {
					return action + '=' + restrictions.join( ',' );
				}
				return null;
			} )
			.filter( ( r ) => r )
			.join( ';' );
	};

	HelpPanelLogger.prototype.incrementUserEditCount = function () {
		this.userEditCount++;
	};

	module.exports = HelpPanelLogger;

}() );
