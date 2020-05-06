( function () {
	var Utils = require( '../../utils/ext.growthExperiments.Utils.js' );

	/**
	 * Class for tracking suggested edit sessions.
	 * A suggested edit session starts with a user clicking on a suggested edit task card,
	 * and ends with leaving the page associated with the task. It is tied to a single browser tab
	 * (but can be initiated by opening the task card in link in a new tab). During the session,
	 * the help panel switches to guidance mode; this class identifies the session and stores
	 * information needed for guidance.
	 *
	 * See also HomepageHooks::onBeforePageDisplay().
	 *
	 * @class mw.libs.ge.SuggestedEditSession
	 * @constructor
	 * @internal UseSuggestedEditSession.getInstance()
	 */
	function SuggestedEditSession() {
		/** @var {boolean} Whether we are in a suggested edit session currently. */
		this.active = false;
		/**
		 * @var {int|null} Suggested edit session ID. This will be used in
		 *   EditAttemptStep.editing_session_id and HelpPanel.help_panel_session_id
		 *   in events logged during the session. It is set via the geclickid URL parameter
		 *   (which is how a suggested edit session starts).
		 */
		this.clickId = null;
		/** @var {mw.Title|null} The target page of the suggested editing task. */
		this.title = null;
		/** @var {string|null} Task type ID of the suggested editing task. */
		this.taskType = null;
		/** @var {string|null} The editor used last in the suggested edit session. */
		this.editorInterface = null;
	}

	/**
	 * Initialize the suggested edit session. This should be called on every page load.
	 * Depending on the situation, it might start a new session, load an existing one,
	 * terminate an existing one, or do nothing.
	 */
	SuggestedEditSession.prototype.initialize = function () {
		if ( this.maybeStart() ) {
			// We are right after the user clicking through a task card. Ignore all existing state.
			this.save();
		} else {
			this.maybeRestore();
		}

		if ( this.active ) {
			this.updateLinks();
		}
	};

	/**
	 * @return {mw.Title}
	 */
	SuggestedEditSession.prototype.getCurrentTitle = function () {
		var pageName = mw.config.get( 'wgPageName' );
		return new mw.Title( pageName );
	};

	/**
	 * Save the session to sessionStorage (meaning it only lives as long as the current
	 * browser tab) and also cache it in the current execution context.
	 */
	SuggestedEditSession.prototype.save = function () {
		if ( !this.active ) {
			throw new Error( 'Trying to save an inactive suggested edit session' );
		}
		mw.storage.session.setObject( 'ge-suggestededit-session', {
			clickId: this.clickId,
			title: this.title.getPrefixedText(),
			taskType: this.taskType,
			editorInterface: this.editorInterface
		} );
		mw.config.set( 'ge-suggestededit-session', this );
	};

	/**
	 * Restore the stored suggested edit session into the current object. If it does not
	 * match the current request, terminate the session.
	 * @return {boolean} Whether an active session was successfully restored.
	 */
	SuggestedEditSession.prototype.maybeRestore = function () {
		var currentTitle, savedTitle,
			data = mw.storage.session.getObject( 'ge-suggestededit-session' );

		if ( this.active ) {
			throw new Error( 'Trying to load an already started suggested edit session' );
		}

		if ( data ) {
			try {
				currentTitle = this.getCurrentTitle();
				savedTitle = new mw.Title( data.title );
			} catch ( e ) {
				// handled at the end of the block
			}
			if ( currentTitle && savedTitle &&
				currentTitle.getSubjectPage().getPrefixedText() === savedTitle.getPrefixedText()
			) {
				this.active = true;
				this.clickId = data.clickId;
				this.title = savedTitle;
				this.taskType = data.taskType;
				this.editorInterface = data.editorInterface;
			} else {
				mw.storage.session.remove( 'ge-suggestededit-session' );
			}
		}
		return this.active;
	};

	/**
	 * See if the user has just started a suggested edit session (which is identiifed by a
	 * URL parameter).
	 * @return {boolean} Whether the session has been initiated.
	 */
	SuggestedEditSession.prototype.maybeStart = function () {
		var url = new mw.Uri();

		if ( this.active ) {
			throw new Error( 'Trying to start an already started active edit session' );
		}

		if ( url.query.geclickid ) {
			this.active = true;
			this.clickId = url.query.geclickid;
			this.title = this.getCurrentTitle();
			this.taskType = url.query.getasktype || null;

			Utils.removeQueryParam( url, 'geclickid' );
			if ( url.query.getasktype ) {
				Utils.removeQueryParam( url, 'getasktype' );
			}
		}

		return this.active;
	};

	/**
	 * Change the URL of edit links to propagate the editing session ID to certain log records.
	 */
	SuggestedEditSession.prototype.updateLinks = function () {
		var self = this;

		$( function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '#ca-edit a, a#ca-edit, #ca-ve-edit a, a#ca-ve-edit, .mw-editsection a' ).each( function () {
				var linkUrl = new mw.Uri( $( this ).attr( 'href' ) );
				linkUrl.extend( {
					editingStatsId: self.clickId,
					editingStatsOversample: 1
				} );
				$( this ).attr( 'href', linkUrl.toString() );
			} );
		} );
	};

	/**
	 * Get a suggested edit session. This is the entry point for other code using this class.
	 * @return {mw.libs.ge.SuggestedEditSession}
	 */
	SuggestedEditSession.getInstance = function () {
		var session = mw.config.get( 'ge-suggestededit-session' );

		if ( session ) {
			return session;
		}
		session = new SuggestedEditSession();
		session.initialize();
		mw.config.set( 'ge-suggestededit-session', session );
		return session;
	};

	// Always initiate. We need to do this to be able to terminate the session when the user
	// navigates away from the target page.
	SuggestedEditSession.getInstance();

	module.exports = SuggestedEditSession;
}() );
