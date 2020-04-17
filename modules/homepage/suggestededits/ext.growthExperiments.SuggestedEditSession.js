( function () {
	var Utils = require( '../../utils/ext.growthExperiments.Utils.js' );

	/**
	 * Class for tracking suggested edit sessions and triggering actions related to them.
	 *
	 * A suggested edit session starts with a user clicking on a suggested edit task card,
	 * and ends with leaving the page associated with the task. It is tied to a single browser tab
	 * (but can be initiated by opening the task card in link in a new tab). During the session,
	 * the help panel switches to guidance mode; this class identifies the session, stores
	 * information needed for guidance, provides information for logging (some of it via its
	 * methods, some by adding tracking to URLs on setup), and triggers opening the post-edit
	 * dialog.
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
		 *   (which is how a suggested edit session starts) and reset during page save.
		 */
		this.clickId = null;
		/** @var {mw.Title|null} The target page of the suggested editing task. */
		this.title = null;
		/** @var {string|null} Task type ID of the suggested editing task. */
		this.taskType = null;
		/** @var {string|null} The editor used last in the suggested edit session. */
		this.editorInterface = null;
		/**
		 * @var {boolean} Show the post-edit dialog at the next opportunity. This is used to
		 *   work around page reloads after saving the page, when the dialog cannot be displayed
		 *   immediately when we detect the save.
		 */
		this.postEditDialogNeedsToBeShown = false;
		/** @var {boolean} Whether the post-edit dialog was already shown in this session. */
		this.postEditDialogShown = false;
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
			this.maybeShowPostEditDialog();
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
			editorInterface: this.editorInterface,
			postEditDialogNeedsToBeShown: this.postEditDialogNeedsToBeShown,
			postEditDialogShown: this.postEditDialogShown
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
				this.postEditDialogNeedsToBeShown = data.postEditDialogNeedsToBeShown;
				this.postEditDialogShown = data.postEditDialogShown;
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
	 * Display the post-edit dialog, and deal with some editors reloading the page immediately
	 * after save.
	 * @param {Object} config
	 * @param {boolean} [config.resetSession] Reset the session ID. This should be done when the
	 *   dialog is displayed, but it should not be done twice if this method is called twice
	 *   due to a reload.
	 * @param {boolean} [config.nextRequest] Don't try to display the dialog, schedule it for the
	 *   next request instead. This is less fragile when we know for sure the editor will reload.
	 * @return {jQuery.Promise} A promise that resolves when the dialog is displayed.
	 */
	SuggestedEditSession.prototype.showPostEditDialog = function ( config ) {
		var self = this;

		if ( config.resetSession ) {
			self.clickId = mw.user.generateRandomSessionId();
			// Need to update the click ID in edit links as well.
			self.updateLinks();
		}
		// The mobile editor and in some configurations the visual editor immediately reloads
		// after saving and firing the post-edit event, so displaying the dialog would fail.
		// Preventing that reload would be fragile, given that the post-edit dialog offers
		// users an "edit again" option. Instead, use the session to display the dialog again
		// after the reload if needed.
		this.postEditDialogNeedsToBeShown = true;
		this.save();

		if ( !config.nextRequest && mw.config.get( 'wgGENewcomerTasksGuidanceEnabled' ) ) {
			return mw.loader.using( 'ext.growthExperiments.PostEdit' ).then( function ( require ) {
				var promise,
					PostEdit = require( 'ext.growthExperiments.PostEdit' );

				if ( self.postEditDialogShown ) {
					// Wrap in a promise to keep symmetry with setupPanel()
					promise = $.when( PostEdit.setupPanelWithoutTask() );
				} else {
					promise = PostEdit.setupPanel();
				}
				return promise.then( function ( result ) {
					result.openPromise.done( function () {
						self.postEditDialogNeedsToBeShown = false;
						self.postEditDialogShown = true;
						self.save();
					} );
					return result.openPromise;
				} );
			} );
		}
		return $.Deferred().resolve().promise();
	};

	/**
	 * Display the post-edit dialog if we are in a suggested edit session, right after an edit.
	 */
	SuggestedEditSession.prototype.maybeShowPostEditDialog = function () {
		var self = this;

		if ( this.postEditDialogNeedsToBeShown ) {
			this.showPostEditDialog( {} );
		}

		// Do this even if we have just shown the dialog above. This is important when the user
		// edits again right after dismissing the dialog.
		mw.hook( 'postEdit' ).add( function () {
			self.showPostEditDialog( { resetSession: true } );
		} );
		mw.hook( 'postEditMobile' ).add( function () {
			self.showPostEditDialog( { resetSession: true, nextRequest: true } );
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
