'use strict';

( function () {
	var PostEditDrawer = require( './PostEditDrawer.js' ),
		PostEditPanel = require( './PostEditPanel.js' ),
		TryNewTaskPanel = require( './TryNewTaskPanel.js' ),
		HelpPanelLogger = require( '../utils/HelpPanelLogger.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		helpConfig = require( '../ext.growthExperiments.Help/data.json' ),
		SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
		suggestedEditSession = SuggestedEditSession.getInstance(),
		newcomerTaskLogger = new NewcomerTaskLogger(),
		postEditPanelHelpPanelLogger = new HelpPanelLogger( helpConfig.GEHelpPanelLoggingEnabled, {
			context: 'postedit',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} ),
		tryNewTaskHelpPanelLogger = new HelpPanelLogger( helpConfig.GEHelpPanelLoggingEnabled, {
			context: 'postedit-trynewtask',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} ),
		hasEditorOpenedSincePageLoad = true,
		rootStore = require( 'ext.growthExperiments.DataStore' ),
		CONSTANTS = rootStore.CONSTANTS,
		ALL_TASK_TYPES = CONSTANTS.ALL_TASK_TYPES,
		tasksStore = rootStore.newcomerTasks,
		filtersStore = tasksStore.filters;

	/**
	 * Attach a handler to be called when the editor is re-opened
	 *
	 * @param {Function} handler
	 */
	function addEditorReopenedHandler( handler ) {
		var shouldExecuteHandler = !hasEditorOpenedSincePageLoad,
			onEditorOpened = function () {
				// The handler can be called when it's first attached if the editor has been opened
				// since the initial page load (the handler gets called if an event was previously
				// fired before the handler is attached) but in this case we only care
				// about the editor being opened after the post-edit drawer is shown.
				if ( shouldExecuteHandler ) {
					handler();
				} else {
					shouldExecuteHandler = true;
				}
			};
		if ( OO.ui.isMobile() ) {
			mw.hook( 'mobileFrontend.editorOpened' ).add( onEditorOpened );
		} else {
			mw.hook( 've.activationComplete' ).add( onEditorOpened );
			mw.hook( 'wikipage.editform' ).add( onEditorOpened );
		}
	}

	/**
	 * Display the given panel, using a mobile or desktop format as appropriate.
	 * Also handles some of the logging.
	 *
	 * @param {mw.libs.ge.PostEditPanel} postEditPanel
	 * @param {mw.libs.ge.HelpPanelLogger} logger
	 * @param {boolean} showToast Whether the panel should show its predefined toast message when opening.
	 * @return {Object} An object with:
	 *   - openPromise {jQuery.Promise} A promise that resolves when the dialog has been displayed.
	 *   - closePromise {jQuery.Promise} A promise that resolves when the dialog has been closed.
	 */
	function displayPanel( postEditPanel, logger, showToast ) {
		if ( mw.libs.ge && mw.libs.ge.HelpPanel ) {
			// It doesn't make any sense to show the help panel in an open state alongside
			// the post-edit panel or the try new task panel, so close it.
			mw.libs.ge.HelpPanel.close();
		}

		var drawer = new PostEditDrawer( postEditPanel, logger ),
			lifecycle,
			closePromise;
		$( document.body ).append( drawer.$element );
		if ( showToast ) {
			lifecycle = drawer.showWithToastMessage();
		} else {
			lifecycle = drawer.openWithIntroContent();
		}
		closePromise = lifecycle.closed.done( function () {
			postEditPanel.logClose();
		} );
		addEditorReopenedHandler( function () {
			drawer.close();
		} );
		return {
			openPromise: lifecycle.opened,
			closePromise: closePromise
		};
	}

	/**
	 * Helper method to tie getNextTask() and displayPanel() together.
	 *
	 * @param {boolean} isDialogShownUponReload Whether the dialog is shown upon page reload.
	 * @param {boolean} showToast Whether the panel should show its predefined toast message when opening.
	 * @return {Object} An object with:
	 *   - task: task data as a plain Object (as returned by GrowthTasksApi), omitted
	 *     when loading the task failed and when the task parameter is null;
	 *   - errorMessage: error message (only when loading the task failed);
	 *   - panel: the mw.libs.ge.PostEditPanel object;
	 *   - openPromise: a promise that resolves when the panel has been displayed.
	 *   - closePromise: A promise that resolves when the dialog has been closed.
	 */
	function setup( isDialogShownUponReload, showToast ) {
		var postEditPanel, displayPanelPromises,
			imageRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'image-recommendation' ] || {},
			imageRecommendationDailyTasksExceeded =
				imageRecommendationQualityGates.dailyLimit || false,
			linkRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'link-recommendation' ] || {},
			linkRecommendationDailyTasksExceeded =
				linkRecommendationQualityGates.dailyLimit || false;

		hasEditorOpenedSincePageLoad = !isDialogShownUponReload;

		postEditPanel = new PostEditPanel( {
			taskType: suggestedEditSession.taskType,
			taskState: suggestedEditSession.taskState,
			taskTypes: ALL_TASK_TYPES,
			newcomerTasksStore: tasksStore,
			newcomerTaskLogger: newcomerTaskLogger,
			helpPanelLogger: postEditPanelHelpPanelLogger,
			imageRecommendationDailyTasksExceeded: imageRecommendationDailyTasksExceeded,
			linkRecommendationDailyTasksExceeded: linkRecommendationDailyTasksExceeded
		} );

		displayPanelPromises = displayPanel( postEditPanel, postEditPanelHelpPanelLogger, showToast );

		return {
			panel: postEditPanel,
			openPromise: displayPanelPromises.openPromise,
			closePromise: displayPanelPromises.closePromise
		};
	}

	module.exports = {

		/**
		 * Create and show the post-edit dialog panel.
		 *
		 * @param {boolean} [isDialogShownUponReload] Whether the post-edit panel is being shown
		 *  after a page reload. This is used to determine whether the editor has been opened
		 *  since the page loads.
		 * @param {string} nextSuggestedTaskType If provided, use only this task type for fetching
		 *   tasks. Used when we are prompting the user to try a new task type after completing
		 *   a certain number of tasks of the current task type. See the LevelingUpManager service.
		 * @param {boolean} showToast Whether the panel should show its predefined toast message when opening.
		 *
		 * @return {Object} An object with:
		 *   - task: task data as a plain Object (as returned by GrowthTasksApi), might be omitted
		 *     when loading the task failed;
		 *   - errorMessage: error message (only when loading the task failed);
		 *   - panel: the mw.libs.ge.PostEditPanel object;
		 *   - openPromise: a promise that resolves when the panel has been displayed.
		 *   - closePromise: A promise that resolves when the dialog has been closed.
		 */
		setupPanel: function ( isDialogShownUponReload, nextSuggestedTaskType, showToast ) {
			var setupResult,
				fetchTasksConfig = {
					excludePageId: mw.config.get( 'wgArticleId' ),
					excludeExceededQuotaTaskTypes: true
				},
				logPanelImpression = function ( panel, errorMessage ) {
					return panel.logImpression.bind( panel, {
						savedTaskType: suggestedEditSession.taskType,
						errorMessage: errorMessage,
						userTaskTypes: filtersStore.getSelectedTaskTypes(),
						userTopics: filtersStore.getSelectedTopics(),
						newcomerTaskToken: suggestedEditSession.newcomerTaskToken
					} );
				};

			if ( nextSuggestedTaskType ) {
				fetchTasksConfig.newTaskTypes = [ nextSuggestedTaskType ];
			}

			tasksStore.fetchTasks( 'postEditDialog', fetchTasksConfig ).catch( function ( errorMessage ) {
				if ( errorMessage ) {
					mw.log.error( errorMessage );
					mw.errorLogger.logError( new Error( errorMessage ), 'error.growthexperiments' );
				}
				setupResult.openPromise.done( logPanelImpression( setupResult.panel, errorMessage ) );
			} );
			setupResult = setup( isDialogShownUponReload, showToast );
			setupResult.openPromise.done( logPanelImpression( setupResult.panel ) );
			return setupResult;
		},
		/**
		 * Create and maybe show the try-new-task dialog panel.
		 *
		 * @return {jQuery.Promise<undefined|null|string>} If "nextSuggestedTasKType" is set in the suggested edit session,
		 *   return a promise that resolves when the try new task panel is closed; otherwise return
		 *   an immediately-resolved promise. The possible return values are:
		 *   - undefined: the panel was not shown because it didn't meet the conditions to do so
		 *   - null: the panel was shown and it was dismissed by the user
		 *   - string: the panel was shown and it was accepted by the user. The returned string represents
		 *  a valid task type id with the next suggested task type.
		 */
		setupTryNewTaskPanel: function () {
			var tryNewTaskOptOuts = mw.config.get( 'wgGELevelingUpTryNewTaskOptOuts', [] );
			if ( SuggestedEditSession.static.shouldShowLevelingUpFeatures() &&
				// A next suggested task type is available for the user
				suggestedEditSession.nextSuggestedTaskType &&
				// The user hasn't opted out of seeing the prompt for this task type
				tryNewTaskOptOuts.indexOf( suggestedEditSession.taskType ) === -1
			) {
				var tryNewTaskPanel = new TryNewTaskPanel( {
					nextSuggestedTaskType: suggestedEditSession.nextSuggestedTaskType,
					activeTaskType: suggestedEditSession.taskType,
					helpPanelLogger: tryNewTaskHelpPanelLogger,
					tryNewTaskOptOuts: tryNewTaskOptOuts
				} );
				var displayPanelPromises = displayPanel( tryNewTaskPanel, tryNewTaskHelpPanelLogger, true );
				displayPanelPromises.openPromise.done( function () {
					tryNewTaskPanel.logImpression( {
						'edit-count-for-task-type': suggestedEditSession.editCountByTaskType[ suggestedEditSession.taskType ]
					} );
				} );
				return displayPanelPromises.closePromise;
			}
			return $.Deferred().resolve();
		}
	};
}() );
