'use strict';

( function () {
	var PostEditDrawer = require( './PostEditDrawer.js' ),
		PostEditPanel = require( './PostEditPanel.js' ),
		HelpPanelLogger = require( '../ext.growthExperiments.Help/HelpPanelLogger.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		helpConfig = require( '../ext.growthExperiments.Help/data.json' ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		newcomerTaskLogger = new NewcomerTaskLogger(),
		helpPanelLogger = new HelpPanelLogger( helpConfig.GEHelpPanelLoggingEnabled, {
			context: 'postedit',
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
	 * @param {PostEditPanel} postEditPanel
	 * @return {Object} An object with:
	 *   - openPromise {jQuery.Promise} A promise that resolves when the dialog has been displayed.
	 *   - closePromise {jQuery.Promise} A promise that resolves when the dialog has been closed.
	 */
	function displayPanel( postEditPanel ) {
		var drawer = new PostEditDrawer( postEditPanel, helpPanelLogger ),
			lifecycle,
			closePromise;
		$( document.body ).append( drawer.$element );
		lifecycle = drawer.showWithToastMessage();
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
	 * Update the UI based on the current state of the task queue
	 *
	 * @param {PostEditPanel} postEditPanel
	 */
	function updateUiBasedOnCurrentStates( postEditPanel ) {
		postEditPanel.togglePrevNavigation( tasksStore.hasPreviousTask() );
		postEditPanel.toggleNextNavigation( tasksStore.hasNextTask() );
		postEditPanel.updatePager( tasksStore.getQueuePosition() + 1, tasksStore.getTaskCount() );
	}

	/**
	 * Helper method to tie getNextTask() and displayPanel() together.
	 *
	 * @param {mw.libs.ge.TaskData|null} task Task data, or null when the task card should not be
	 *   shown.
	 * @param {string|null} errorMessage Error message, or null when there was no error.
	 * @param {boolean} isDialogShownUponReload Whether the dialog is shown upon page reload.
	 * @return {Object} An object with:
	 *   - task: task data as a plain Object (as returned by GrowthTasksApi), omitted
	 *     when loading the task failed and when the task parameter is null;
	 *   - errorMessage: error message (only when loading the task failed);
	 *   - panel: the PostEditPanel object;
	 *   - openPromise: a promise that resolves when the panel has been displayed.
	 *   - closePromise: A promise that resolves when the dialog has been closed.
	 */
	function setup( task, errorMessage, isDialogShownUponReload ) {
		var postEditPanel, displayPanelPromises, openPromise, result,
			imageRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'image-recommendation' ] || {},
			imageRecommendationDailyTasksExceeded =
				imageRecommendationQualityGates.dailyLimit || false,
			linkRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'link-recommendation' ] || {},
			linkRecommendationDailyTasksExceeded =
				linkRecommendationQualityGates.dailyLimit || false;

		hasEditorOpenedSincePageLoad = !isDialogShownUponReload;

		if ( errorMessage ) {
			mw.log.error( errorMessage );
			mw.errorLogger.logError( new Error( errorMessage ), 'error.growthexperiments' );
		}

		postEditPanel = new PostEditPanel( {
			taskType: suggestedEditSession.taskType,
			taskState: suggestedEditSession.taskState,
			nextTask: task,
			taskTypes: task ? ALL_TASK_TYPES : {},
			newcomerTaskLogger: newcomerTaskLogger,
			helpPanelLogger: helpPanelLogger,
			imageRecommendationDailyTasksExceeded: imageRecommendationDailyTasksExceeded,
			linkRecommendationDailyTasksExceeded: linkRecommendationDailyTasksExceeded
		} );
		postEditPanel.on( 'postedit-prev-task', function () {
			tasksStore.showPreviousTask();
		} );
		postEditPanel.on( 'postedit-next-task', function () {
			tasksStore.showNextTask();
		} );
		updateUiBasedOnCurrentStates( postEditPanel );

		displayPanelPromises = displayPanel( postEditPanel );
		openPromise = displayPanelPromises.openPromise;
		openPromise.done( postEditPanel.logImpression.bind( postEditPanel, {
			savedTaskType: suggestedEditSession.taskType,
			errorMessage: errorMessage,
			userTaskTypes: filtersStore.getSelectedTaskTypes(),
			userTopics: filtersStore.getSelectedTopics(),
			newcomerTaskToken: suggestedEditSession.newcomerTaskToken
		} ) );

		tasksStore.on( CONSTANTS.EVENTS.TASK_QUEUE_CHANGED, function () {
			var currentTask = tasksStore.getCurrentTask();
			if ( currentTask ) {
				postEditPanel.updateNextTask( currentTask );
			}
			updateUiBasedOnCurrentStates( postEditPanel );
		} );
		tasksStore.on( CONSTANTS.EVENTS.CURRENT_TASK_EXTRA_DATA_CHANGED, function () {
			var currentTask = tasksStore.getCurrentTask();
			if ( currentTask ) {
				postEditPanel.updateTask( currentTask );
			}
		} );

		tasksStore.on( CONSTANTS.EVENTS.FETCHED_MORE_TASKS, function ( isLoading ) {
			// Disable next navigation until more tasks are fetched or if there are no more tasks
			var isNextEnabled = !isLoading && tasksStore.hasNextTask();
			postEditPanel.toggleNextNavigation( isNextEnabled );
			postEditPanel.nextButton.setIcon( isLoading ? 'ellipsis' : 'next' );
		} );

		result = {
			panel: postEditPanel,
			openPromise: openPromise,
			closePromise: displayPanelPromises.closePromise
		};
		if ( task ) {
			result.task = task;
		} else if ( errorMessage ) {
			result.errorMessage = errorMessage;
		}
		return result;
	}

	module.exports = {
		/**
		 * Create and show the panel
		 *
		 * @param {boolean} [isDialogShownUponReload] Whether the post-edit panel is being shown
		 *  after a page reload. This is used to determine whether the editor has been opened
		 *  since the page loads.
		 *
		 * @return {jQuery.Promise<Object>} A promise resolving to an object with:
		 *   - task: task data as a plain Object (as returned by GrowthTasksApi), might be omitted
		 *     when loading the task failed;
		 *   - errorMessage: error message (only when loading the task failed);
		 *   - panel: the PostEditPanel object;
		 *   - openPromise: a promise that resolves when the panel has been displayed.
		 *   - closePromise: A promise that resolves when the dialog has been closed.
		 */
		setupPanel: function ( isDialogShownUponReload ) {
			return tasksStore.fetchTasks( 'postEditDialog', {
				excludePageId: mw.config.get( 'wgArticleId' )
			} ).then( function () {
				return setup( tasksStore.getCurrentTask(), null, isDialogShownUponReload );
			}, function ( errorMessage ) {
				return setup( null, errorMessage, isDialogShownUponReload );
			} );
		}
	};
}() );
