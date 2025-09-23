( function () {
	const Utils = require( '../utils/Utils.js' );
	const states = {
		/** Initial task state when the user opens a task. */
		STARTED: 'started',
		/** Task state after the user makes a real edit. */
		SAVED: 'saved',
		/** Task state after the user submits a structured task without making an edit. */
		SUBMITTED: 'submitted',
		/** Task state after the user leaves the workflow without saving or submitting anything. */
		CANCELLED: 'cancelled',
	};
	const allStates = [ states.STARTED, states.SAVED, states.SUBMITTED, states.CANCELLED ];

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
	 * @private Use SuggestedEditSession.getInstance()
	 */
	function SuggestedEditSession() {
		OO.EventEmitter.call( this );

		// These variables are persisted for the lifetime of the session.

		/** @member {boolean} Whether we are in a suggested edit session currently. */
		this.active = false;
		/**
		 * @member {number|string|null} Suggested edit session ID. This will be used in
		 *   EditAttemptStep.editing_session_id and HelpPanel.help_panel_session_id
		 *   in events logged during the session. It is set via the geclickid URL parameter
		 *   (which is how a suggested edit session starts) and reset during page save.
		 */
		this.clickId = null;
		/** @member {mw.Title|null} The target page of the suggested editing task. */
		this.title = null;
		/** @member {string|null} Task type ID of the suggested editing task. */
		this.taskType = null;
		/**
		 * @member {string|null|undefined} Task type ID of the next suggested task type, if any.
		 *  It is undefined if we have not yet attempted to fetch the data from the API.
		 */
		this.nextSuggestedTaskType = undefined;
		/**
		 * @member {Object} Task type IDs are the keys, the values are the edit count
		 *  for the task type.
		 */
		this.editCountByTaskType = {};
		/**
		 * @member {Object|null} Tasktype-specific task data. Not all task types have this.
		 *   An 'error' field being present means that loading the task data failed (the
		 *   field will contain an error message); callers are expected to handle this gracefully.
		 */
		this.taskData = null;
		/** @member {string} Task state; one of the STATES constants. */
		this.taskState = null;
		/** @member {string|null} The editor used last in the suggested edit session. */
		this.editorInterface = null;
		/**
		 * @member {boolean} Show the post-edit dialog at the next opportunity. This is used to
		 *   show the post-edit dialog after the next page reload.
		 */
		this.postEditDialogNeedsToBeShown = false;
		/**
		 * @member {number|null} The revision ID associated with the suggested edit that occurred
		 *   during the session.
		 */
		this.newRevId = null;
		/** @member {boolean} Whether the mobile peek was already shown in this session. */
		this.mobilePeekShown = false;
		/** @member {boolean} Whether the help panel should be locked to its current panel. */
		this.helpPanelShouldBeLocked = false;
		/** @member {string|null} The current panel shown in the help panel. */
		this.helpPanelCurrentPanel = null;
		/** @member {boolean} Whether the help panel should open on page load. */
		this.helpPanelShouldOpen = true;
		/** @member {string|null} The current help panel tip visible. */
		this.helpPanelCurrentTip = null;
		/**
		 * @member {boolean} Whether the user interacted with the help panel in
		 * the suggested edits screen by navigating or clicking tips.
		 */
		this.helpPanelSuggestedEditsInteractionHappened = false;
		/** @member {boolean} Whether the article should be opened in edit mode after loading. */
		this.shouldOpenArticleInEditMode = false;
		/**
		 * @member {boolean} Whether onboarding dialog needs to be shown for the session
		 * This prevents onboading dialog from being shown multiple times during the same session.
		 * This is separate from user preference to dismiss onboarding altogether.
		 */
		this.onboardingNeedsToBeShown = true;
		/** @member {string|null} The newcomer task token set from NewcomerTaskLogger#log. */
		this.newcomerTaskToken = null;

		/**
		 * @member {Object} Persist data related to quality gates as the user interacts with the
		 * suggested edit. Used currently for keeping track of the daily limit gate for image
		 * recommendation on Minerva.
		 */
		this.qualityGateConfig = {};

		// These variables are not persisted and only used for immediate state management.

		/** @member {boolean} Flag to prevent double-opening of the post-edit dialog (T283120) */
		this.postEditDialogIsOpen = false;
		/**
		 * @member {boolean} Whether the Navigation Timing API is available
		 */
		this.shouldTrackPerformance = typeof window.performance !== 'undefined';
		/**
		 * @member {number} Timestamp when the suggested edit session starts.
		 * Used for tracking load times for newcomer tasks.
		 */
		this.startTime = this.shouldTrackPerformance ? window.performance.now() : 0;
	}
	OO.mixinClass( SuggestedEditSession, OO.EventEmitter );

	/** pseudo-constants for the 'taskState' property */
	SuggestedEditSession.static.STATES = states;

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
			this.suppressNotices();
			this.updateEditorInterface();
			this.updateEditLinkUrls();
			this.maybeShowPostEditDialog();
			// Attempt to fetch next suggested task type if not already known.
			if ( this.nextSuggestedTaskType === undefined &&
				SuggestedEditSession.static.shouldShowLevelingUpFeatures()
			) {
				// We don't need to wait for the promise to resolve here. Presumably, we have enough
				// time before the user gets to the point of making an edit for the API request
				// to complete.
				this.getNextSuggestedTaskType();
			}
		}
	};

	/**
	 * @return {mw.Title}
	 */
	SuggestedEditSession.prototype.getCurrentTitle = function () {
		const pageName = mw.config.get( 'wgPageName' );
		return new mw.Title( pageName );
	};

	/**
	 * Save the session to sessionStorage (meaning it only lives as long as the current
	 * browser tab) and also cache it in the current execution context.
	 */
	SuggestedEditSession.prototype.save = function () {
		const session = {
			clickId: this.clickId,
			title: this.title.getPrefixedText(),
			taskType: this.taskType,
			nextSuggestedTaskType: this.nextSuggestedTaskType,
			editCountByTaskType: this.editCountByTaskType,
			taskData: this.taskData,
			taskState: this.taskState,
			editorInterface: this.editorInterface,
			postEditDialogNeedsToBeShown: this.postEditDialogNeedsToBeShown,
			newRevId: this.newRevId,
			mobilePeekShown: this.mobilePeekShown,
			helpPanelShouldBeLocked: this.helpPanelShouldBeLocked,
			helpPanelCurrentPanel: this.helpPanelCurrentPanel,
			helpPanelShouldOpen: this.helpPanelShouldOpen,
			helpPanelCurrentTip: this.helpPanelCurrentTip,
			helpPanelSuggestedEditsInteractionHappened:
			this.helpPanelSuggestedEditsInteractionHappened,
			onboardingNeedsToBeShown: this.onboardingNeedsToBeShown,
			newcomerTaskToken: this.newcomerTaskToken,
			shouldOpenArticleInEditMode: this.shouldOpenArticleInEditMode,
			qualityGateConfig: this.qualityGateConfig,
		};
		if ( !this.active ) {
			throw new Error( 'Trying to save an inactive suggested edit session' );
		}
		mw.storage.session.setObject( 'ge-suggestededit-session', session );
		mw.config.set( 'ge-suggestededit-session', this );
		this.emit( 'save', this );
	};

	/**
	 * Restore the stored suggested edit session into the current object. If it does not
	 * match the current request, terminate the session.
	 *
	 * @return {boolean} Whether an active session was successfully restored.
	 */
	SuggestedEditSession.prototype.maybeRestore = function () {
		const data = mw.storage.session.getObject( 'ge-suggestededit-session' );

		if ( this.active ) {
			throw new Error( 'Trying to load an already started suggested edit session' );
		}

		if ( data ) {
			let currentTitle, savedTitle;
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
				this.nextSuggestedTaskType = data.nextSuggestedTaskType;
				this.editCountByTaskType = data.editCountByTaskType;
				this.taskData = data.taskData;
				this.taskState = data.taskState;
				this.editorInterface = data.editorInterface;
				this.postEditDialogNeedsToBeShown = data.postEditDialogNeedsToBeShown;
				this.newRevId = data.newRevId;
				this.mobilePeekShown = data.mobilePeekShown;
				this.helpPanelShouldBeLocked = data.helpPanelShouldBeLocked;
				this.helpPanelCurrentPanel = data.helpPanelCurrentPanel;
				this.helpPanelShouldOpen = data.helpPanelShouldOpen;
				this.helpPanelCurrentTip = data.helpPanelCurrentTip;
				this.helpPanelSuggestedEditsInteractionHappened =
					data.helpPanelSuggestedEditsInteractionHappened;
				this.onboardingNeedsToBeShown = data.onboardingNeedsToBeShown;
				this.newcomerTaskToken = data.newcomerTaskToken;
				this.shouldOpenArticleInEditMode = data.shouldOpenArticleInEditMode;
				this.qualityGateConfig = data.qualityGateConfig;
			} else {
				mw.storage.session.remove( 'ge-suggestededit-session' );
			}
		}
		return this.active;
	};

	/**
	 * See if the user has just started a suggested edit session (which is identified by a
	 * URL parameter).
	 *
	 * @return {boolean} Whether the session has been initiated.
	 */
	SuggestedEditSession.prototype.maybeStart = function () {
		const url = new URL( window.location.href );

		if ( this.active ) {
			throw new Error( 'Trying to start an already started active edit session' );
		}

		if ( url.searchParams.get( 'geclickid' ) ) {
			this.active = true;
			this.clickId = url.searchParams.get( 'geclickid' );
			this.title = this.getCurrentTitle();
			this.taskType = mw.config.get( 'wgGESuggestedEditTaskType' );
			this.taskData = mw.config.get( 'wgGESuggestedEditData' );
			this.qualityGateConfig = mw.config.get( 'wgGESuggestedEditQualityGateConfig' ) || {};
			this.taskState = states.STARTED;

			Utils.removeQueryParam( url, 'geclickid' );
			if ( url.searchParams.get( 'getasktype' ) ) {
				Utils.removeQueryParam( url, 'getasktype' );
			}
		}

		if ( url.searchParams.get( 'genewcomertasktoken' ) ) {
			this.newcomerTaskToken = url.searchParams.get( 'genewcomertasktoken' );
			Utils.removeQueryParam( url, 'genewcomertasktoken' );
		}

		// url.query.gesuggestededit is not removed from the URL, because we need it to survive
		// page reloads for any code that depends on its presence (e.g. loading the help panel
		// when the preference is switched off T284088)

		// Don't show help panel & mobile peek if the article is in edit mode
		this.shouldOpenArticleInEditMode = url.searchParams.get( 'veaction' ) === 'edit';
		this.helpPanelShouldOpen = !this.shouldOpenArticleInEditMode;
		this.mobilePeekShown = this.shouldOpenArticleInEditMode;

		return this.active;
	};

	/**
	 * Suppress the core post-edit notice and VE welcome/onboarding dialogs.
	 */
	SuggestedEditSession.prototype.suppressNotices = function () {
		const veState = mw.loader.getState( 'ext.visualEditor.desktopArticleTarget.init' );

		// Prevent the default post-edit notice. This would logically belong to the
		// PostEdit module, but that would load too late.
		mw.config.set( 'wgPostEditConfirmationDisabled', true );
		// Suppress the VisualEditor welcome dialog and education popups
		// Do this only if VE's init module was already going to be loaded; we don't want to trigger
		// it if it wasn't going to be loaded otherwise
		if ( veState === 'loading' || veState === 'loaded' || veState === 'ready' ) {
			mw.loader.using( 'ext.visualEditor.desktopArticleTarget.init' ).then( () => {
				mw.libs.ve.disableWelcomeDialog();
				mw.libs.ve.disableEducationPopups();
			} );
		}
	};

	/**
	 * @param {string} state One of the states.* constants.
	 */
	SuggestedEditSession.prototype.setTaskState = function ( state ) {
		if ( allStates.includes( state ) ) {
			this.taskState = state;
			this.save();
		} else {
			mw.log.error( 'SuggestedEditSession.setTaskState: invalid state ' + state );
			mw.errorLogger.logError( new Error( 'SuggestedEditSession.setTaskState: invalid state ' + state ), 'error.growthexperiments' );
		}
	};

	/**
	 * Make the self.editorInterface property keep track of editing mode switches.
	 */
	SuggestedEditSession.prototype.updateEditorInterface = function () {
		const self = this,
			saveEditorChanges = function ( suggestedEditSession, editorInterface ) {
				if ( suggestedEditSession.active &&
					suggestedEditSession.editorInterface !== editorInterface &&
					Utils.isValidEditor( editorInterface )
				) {
					suggestedEditSession.editorInterface = editorInterface;
					suggestedEditSession.save();
				}
			};

		mw.trackSubscribe( 'event.EditAttemptStep', ( _, data ) => {
			saveEditorChanges( self, data.editor_interface );
		} );
		// MobileFrontend has its own schema wrapper
		mw.trackSubscribe( 'mf.schemaEditAttemptStep', ( _, data ) => {
			saveEditorChanges( self, data.editor_interface );
		} );
		// WikiEditor doesn't use mw.track. But it doesn't load dynamically either so
		// we can check it at page load time.
		$( () => {
			const url = new URL( window.location.href );
			// "submit" can be in the URL query if the user switched from VE to source
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( [ 'edit', 'submit' ].includes( url.searchParams.get( 'action' ) ) && $( '#wpTextbox1:visible' ).length ) {
				saveEditorChanges( self, 'wikitext' );
			}
		} );
	};

	/**
	 * Change the URL of edit links to propagate the editing session ID to certain log records,
	 * as well as the 'gesuggestededit' parameter that indicates the user is doing a suggested edit.
	 */
	SuggestedEditSession.prototype.updateEditLinkUrls = function () {
		const self = this,
			linkSelector = '#ca-edit a[href], a#ca-edit[href], #ca-ve-edit a[href], ' +
				'a#ca-ve-edit[href], .mw-editsection a[href]';

		mw.config.set( 'wgWMESchemaEditAttemptStepSamplingRate', 1 );
		$( () => {
			$( linkSelector ).each( function () {
				const linkUrl = new URL( this.href, window.location.origin );
				linkUrl.searchParams.set( 'editingStatsId', self.clickId );
				linkUrl.searchParams.set( 'editingStatsOversample', 1 );
				linkUrl.searchParams.set( 'gesuggestededit', 1 );
				$( this ).attr( 'href', linkUrl.toString() );
			} );
		} );
	};

	/**
	 * Get the next suggested task type for the user, given the active task type in
	 * this.taskType, and update the SuggestedEditSession state with the suggestion.
	 *
	 * @return {jQuery.Promise} A promise that resolves when the API request to the
	 * growthnextsuggestedtasktype query module completes.
	 */
	SuggestedEditSession.prototype.getNextSuggestedTaskType = function () {
		const apiParams = {
			action: 'query',
			meta: 'growthnextsuggestedtasktype',
			gnsttactivetasktype: this.taskType,
		};
		return new mw.Api().post( apiParams ).then( ( result ) => {
			this.nextSuggestedTaskType = result.query.growthnextsuggestedtasktype;
			this.editCountByTaskType = result.query.editcountbytasktype;
		} );
	};

	/**
	 * Try to display the post-edit dialog, and deal with some editors reloading the page
	 * immediately after save by setting an "intent to show" flag in sessionStorage, which
	 * will trigger maybeShowPostEditDialog() on the next request if the dialog hasn't been
	 * shown yet.
	 *
	 * @param {Object} config
	 * @param {boolean} [config.resetSession] Reset the session ID. This should be done when the
	 *   dialog is displayed, but it should not be done twice if this method is called twice
	 *   due to a reload.
	 * @param {boolean} [config.nextRequest] Don't try to display the dialog, schedule it for the
	 *   next request instead. This is less fragile when we know for sure the editor will reload.
	 * @param {number|null} [config.newRevId] The revision ID associated with the suggested edit.
	 *   Will only be set for edits done via mobile.
	 */
	SuggestedEditSession.prototype.showPostEditDialog = function ( config ) {
		const url = new URL( window.location.href );
		const self = this;

		config = config || {};
		// T283120: avoid opening the dialog multiple times at once. This shouldn't be
		// happening but with the various delayed mechanisms for opening the dialog, it's
		// hard to avoid.
		if ( this.postEditDialogIsOpen ) {
			return;
		}

		if ( config.resetSession ) {
			self.clickId = mw.user.generateRandomSessionId();
			self.newRevId = null;
			// Need to update the click ID in edit links as well.
			self.updateEditLinkUrls();
		}

		this.postEditDialogNeedsToBeShown = true;
		this.newRevId = self.newRevId || config.newRevId;
		this.save();

		if ( !config.nextRequest &&
			// Never show the dialog on an edit screen, just defer it to the next request.
			// This can happen when VisualEditor fires the postEdit hook before reloading the page.
			!( url.searchParams.get( 'veaction' ) || url.searchParams.get( 'action' ) === 'edit' )
		) {
			this.postEditDialogIsOpen = true;
			mw.hook( 'helpPanel.hideCta' ).fire();

			const postEditDialogClosePromise = mw.loader.using( 'ext.growthExperiments.PostEdit' ).then( ( require ) => require( 'ext.growthExperiments.PostEdit' ).setupTryNewTaskPanel().then( ( tryNewTaskResult ) => {
				// Prepare for follow-up edits by loading the next suggested task
				// type based on the edit just now made.
				if ( SuggestedEditSession.static.shouldShowLevelingUpFeatures() ) {
					self.getNextSuggestedTaskType().then( () => {
						self.save();
					} );
				}

				if ( tryNewTaskResult.shown && tryNewTaskResult.closeData === undefined ) {
					// The user aborted the try new task dialog, probably by clicking on the edit link.
					// Do not show the post-edit dialog.
					return $.Deferred().resolve().promise();
				}

				const postEditDialogLifecycle = require( 'ext.growthExperiments.PostEdit' ).setupPanel(
					tryNewTaskResult.closeData,
					!tryNewTaskResult.shown,
				);
				postEditDialogLifecycle.openPromise.then( () => {
					self.postEditDialogNeedsToBeShown = false;
					self.save();
					if ( self.editorInterface !== 'visualeditor' ) {
						// VisualEditor edits will receive change tags through
						// ve.init.target.saveFields and VE's PostSave hook implementation
						// in GrowthExperiments.
						// For non-VisualEditor-edits, we'll query the revision that was just
						// saved, and send a POST to the newcomertask/complete endpoint to apply
						// the relevant change tags.
						self.tagNonVisualEditorEditWithGrowthChangeTags( self.taskType );
					}
				} );
				return postEditDialogLifecycle.closePromise;
			} ) );

			postEditDialogClosePromise.then( () => {
				// Make sure we'll show the dialog again if the page is edited again in VE
				// without a page reload.
				self.postEditDialogIsOpen = false;
			} );
		}
	};

	/**
	 * Get the most recent revision to the article by the current user and tag it with the relevant
	 * change tags for the task type.
	 *
	 * @param {string} taskType
	 * @return {jQuery.Promise} that resolves after the POST to the newcomer change tags
	 * manager endpoint
	 * is complete.
	 */
	SuggestedEditSession.prototype.tagNonVisualEditorEditWithGrowthChangeTags = function (
		taskType,
	) {
		const revIdPromise = this.newRevId ? $.Deferred().resolve().promise() : new mw.Api().get( {
			action: 'query',
			prop: 'revisions',
			pageids: mw.config.get( 'wgRelevantArticleId' ),
			rvprop: 'ids|tags',
			rvlimit: 1,
			rvuser: mw.config.get( 'wgUserName' ),
		} );
		return revIdPromise.then( ( data ) => {
			// We didn't have the new revision ID already, so get it from the API response.
			if ( !this.newRevId && data && data.query && data.query.pages ) {
				const response = data.query.pages[ Object.keys( data.query.pages )[ 0 ] ];
				this.newRevId = response.revisions[ 0 ].revid;
			}
			if ( !this.newRevId ) {
				mw.log.error( 'Unable to find a revision to apply edit tags to, no edit tags will be applied.' );
				mw.errorLogger.logError( new Error( 'Unable to find a revision to apply edit tags to, no edit tags will be applied.' ), 'error.growthexperiments' );
			}
			const apiUrl = '/growthexperiments/v0/newcomertask/complete';
			return new mw.Rest().post( apiUrl + '?' + $.param( { taskTypeId: taskType, revId: this.newRevId } ) )
				.then( () => {}, ( err, errObject ) => {
					mw.log.error( errObject );
					let errMessage = errObject.exception;
					if ( errObject.xhr &&
						errObject.xhr.responseJSON &&
						errObject.xhr.responseJSON.messageTranslations
					) {
						errMessage = errObject.xhr.responseJSON.messageTranslations.en;
					}
					mw.errorLogger.logError( new Error( errMessage ), 'error.growthexperiments' );
					throw new Error( errMessage );
				} );
		} );
	};

	/**
	 * Display the post-edit dialog if we are in a suggested edit session, right after a suggested
	 * edit. Also, set up postEdit[Mobile] hook handlers for displaying the post-edit dialog
	 * if an edit happens later.
	 *
	 * This gets called at the beginning of every request in a suggested edit session, and needs
	 * to handle both the situation where an edit happened via a mechanism that does not cause
	 * a page reload, and when this request was caused by a page reload after an edit.
	 *
	 * Possible mechanisms for showing the dialog:
	 * - Structured task with non-null edits: manually set postEditDialogNeedsToBeShown
	 *   flag via StructuredTaskArticleTarget.saveComplete (the page is reloaded upon save,
	 *   unlike regular edits in order to change the article target, see T308046)
	 * - Unstructured task: via postEdit or postEditMobile hooks
	 * - Unstructured task on mobile, or some VE edge cases, when maybeShowPostEditDialog was
	 *   called during save, detected that the page is about to be reloaded, and set the
	 *   postEditDialogNeedsToBeShown flag.
	 */
	SuggestedEditSession.prototype.maybeShowPostEditDialog = function () {
		// Exit early if on History page
		if ( mw.config.get( 'wgAction' ) === 'history' ) {
			return;
		}
		const self = this;
		const currentTitle = this.getCurrentTitle();
		const url = new URL( window.location.href );
		const hasSwitchedFromMachineSuggestions = url.searchParams.get( 'hideMachineSuggestions' ) !== null;
		// Only show the post-edit dialog on the task page, not e.g. on talk page edits.
		// Skip the dialog if the user saved an edit w/VE after switching from suggestions mode.
		if ( !currentTitle || !this.title ||
			currentTitle.getPrefixedText() !== this.title.getPrefixedText() ||
			hasSwitchedFromMachineSuggestions
		) {
			return;
		}

		if ( this.postEditDialogNeedsToBeShown ) {
			this.showPostEditDialog();
			// For structured tasks, the edit can only be made once so the postEdit event handlers
			// should not be attached.
			if ( SuggestedEditSession.static.isStructuredTask( this.taskType ) ) {
				return;
			}
		}

		// For unstructured tasks, do this even if we have just shown the dialog above.
		// This is important when the user edits again right after dismissing the dialog.
		mw.hook( 'postEdit' ).add( () => {
			self.setTaskState( states.SAVED );
			self.showPostEditDialog( { resetSession: true } );
		} );

		/**
		 * @param {Object} data
		 * @param {number|null} [data.newRevId] ID of the newly created revision, or null if it was
		 *  a null edit.
		 */
		mw.hook( 'postEditMobile' ).add( ( data ) => {
			self.setTaskState( data.newRevId ? states.SAVED : states.SUBMITTED );
			self.showPostEditDialog( {
				resetSession: true,
				newRevId: data.newRevId,
				// VE updates the page dynamically so the post-edit dialog can be shown immediately
				nextRequest: self.editorInterface !== 'visualeditor',
			} );
		} );
	};

	/**
	 * Track the duration for setting up the editing surface
	 *
	 * This is for structured tasks only (since the editor is opened automatically).
	 */
	SuggestedEditSession.prototype.trackEditorReady = function () {
		if ( !this.startTime || !this.shouldTrackPerformance ) {
			return;
		}
		const duration = window.performance.now() - this.startTime;
		mw.track(
			'stats.mediawiki_GrowthExperiments_task_editor_ready_seconds',
			duration,
			{
				// eslint-disable-next-line camelcase
				task_type: Utils.normalizeLabelForStats( this.taskType ),
				platform: ( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
				operation: 'editor_shown',
				wiki: mw.config.get( 'wgDBname' ),
			},
		);
	};

	/**
	 * Track the duration for setting up the guidance panel
	 *
	 * This is for unstructured tasks only.
	 * For mobile, this is when the mobile peek (first time with unstructured tasks) or the
	 * help panel button is shown. For desktop, this is when the help panel button is shown.
	 */
	SuggestedEditSession.prototype.trackGuidanceShown = function () {
		// If the editor is opened automatically, taskEditorReady should be used instead.
		if ( this.shouldOpenArticleInEditMode || !this.startTime || !this.shouldTrackPerformance ) {
			return;
		}
		const guidanceDisplayDuration = window.performance.now() - this.startTime;
		mw.track(
			'stats.mediawiki_GrowthExperiments_suggested_edits_session_seconds',
			guidanceDisplayDuration,
			{
				// eslint-disable-next-line camelcase
				task_type: Utils.normalizeLabelForStats( this.taskType ),
				platform: ( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
				operation: 'guidance_shown',
				wiki: mw.config.get( 'wgDBname' ),
			},
		);
	};

	/**
	 * Clear session-specific data and set the task state to SAVED,
	 * used when making non-null edits with structured tasks
	 */
	SuggestedEditSession.prototype.onStructuredTaskSaved = function () {
		// Since the page is reloaded, the postEdit hook won't be fired so the flag is used instead in
		// SuggestedEditSession to show the post-edit dialog.
		this.postEditDialogNeedsToBeShown = true;
		// After saving, the clickId is used for the next task.
		this.clickId = mw.user.generateRandomSessionId();
		this.setTaskState( states.SAVED );
		this.save();
	};

	/**
	 * Get a suggested edit session. This is the entry point for other code using this class.
	 *
	 * @return {mw.libs.ge.SuggestedEditSession}
	 */
	SuggestedEditSession.getInstance = function () {
		let session = mw.config.get( 'ge-suggestededit-session' );

		if ( session ) {
			return session;
		}
		session = new SuggestedEditSession();
		session.initialize();
		mw.config.set( 'ge-suggestededit-session', session );
		return session;
	};

	/**
	 * Check if leveling up features are enabled for this user.
	 *
	 * @return {boolean}
	 * @todo remove this once the feature is fully rolled out
	 */
	SuggestedEditSession.static.shouldShowLevelingUpFeatures = function () {
		return mw.config.get( 'wgGELevelingUpEnabledForUser' );
	};

	/**
	 * Check whether the specified task type is a structured task
	 *
	 * @param {string} taskType Name of the task type
	 * @return {boolean}
	 */
	SuggestedEditSession.static.isStructuredTask = function ( taskType ) {
		return [
			'link-recommendation',
			'image-recommendation',
			'section-image-recommendation',
		].includes( taskType );
	};

	// Always initiate. We need to do this to be able to terminate the session when the user
	// navigates away from the target page.
	// To facilitate QA/debugging.
	window.ge = window.ge || {};
	ge.suggestedEditSession = SuggestedEditSession.getInstance();

	/**
	 * Set plugin data for VisualEditor. This is passed onto the pre/post save hooks. We use
	 * the taskType for adding the relevant change tags (e.g. "newcomer task copyedit") to tasks.
	 * Structured tasks already override VisualEditor's ArticleTarget and pass necessary metadata,
	 * so we don't do anything for those.
	 */
	mw.hook( 've.activationComplete' ).add( () => {
		// HACK: Some VE edits end up with editorInterface set to null;
		// set it here to avoid this.
		ge.suggestedEditSession.editorInterface = ge.suggestedEditSession.editorInterface || 'visualeditor';

		if ( ge.suggestedEditSession.taskType === null ||
			SuggestedEditSession.static.isStructuredTask( ge.suggestedEditSession.taskType ) ) {
			return;
		}
		const pluginName = 'ge-task-' + ge.suggestedEditSession.taskType,
			pluginDataKey = 'data-' + pluginName;
		if ( !ve.init.target.saveFields[ pluginDataKey ] ) {
			ve.init.target.saveFields[ pluginDataKey ] = function () {
				// This is redundant data to set, but if pluginData is empty, our VisualEditor pre/post save
				// hooks won't execute, and refactoring that to not check for plugin data is not so straightforward.
				return JSON.stringify( { taskType: ge.suggestedEditSession.taskType } );
			};
			const plugins = ve.init.target.saveFields.plugins ? ve.init.target.saveFields.plugins() : [];
			plugins.push( pluginName );
			ve.init.target.saveFields.plugins = function () {
				return plugins;
			};
		}
	} );

	module.exports = SuggestedEditSession;
}() );
