'use strict';

( function () {
	var PostEditDrawer = require( './PostEditDrawer.js' ),
		PostEditPanel = require( './PostEditPanel.js' ),
		GrowthTasksApi = require( '../ext.growthExperiments.Homepage.SuggestedEdits/GrowthTasksApi.js' ),
		HelpPanelLogger = require( '../ext.growthExperiments.Help/HelpPanelLogger.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		TaskTypesAbFilter = require( '../ext.growthExperiments.Homepage.SuggestedEdits/TaskTypesAbFilter.js' ),
		taskTypes = TaskTypesAbFilter.getTaskTypes(),
		defaultTaskTypes = TaskTypesAbFilter.getDefaultTaskTypes(),
		suggestedEditsConfig = require( '../ext.growthExperiments.Homepage.SuggestedEdits/config.json' ),
		aqsConfig = require( '../ext.growthExperiments.Homepage.SuggestedEdits/AQSConfig.json' ),
		helpConfig = require( '../ext.growthExperiments.Help/data.json' ),
		api = new GrowthTasksApi( {
			taskTypes: taskTypes,
			defaultTaskTypes: defaultTaskTypes,
			aqsConfig: aqsConfig,
			suggestedEditsConfig: suggestedEditsConfig,
			isMobile: OO.ui.isMobile(),
			context: 'postEditDialog'
		} ),
		apiConfig = {
			getDescription: true,
			// 10 tasks are hopefully enough to find one that's not protected.
			size: 10
		},
		preferences = api.getPreferences(),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		isLinkRecommendationTask = ( suggestedEditSession.taskType === 'link-recommendation' ),
		isImageRecommendationTask = ( suggestedEditSession.taskType === 'image-recommendation' ),
		newcomerTaskLogger = new NewcomerTaskLogger(),
		helpPanelLogger = new HelpPanelLogger( helpConfig.GEHelpPanelLoggingEnabled, {
			context: 'postedit',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} ),
		otherTasks = [],
		nextTaskIndex = 0,
		hasEditorOpenedSincePageLoad = true;

	/**
	 * Fetch potential tasks for the next suggested edit.
	 *
	 * @return {jQuery.Promise} A promise that will update otherTasks array and resolve when
	 *   additional tasks have been fetched
	 */
	function fetchOtherTasks() {
		var taskTypesToFetch,
			imageRecommendationQualityGates = suggestedEditSession.qualityGateConfig[ 'image-recommendation' ] || {},
			imageRecommendationDailyTasksExceeded =
				imageRecommendationQualityGates.dailyLimit || false,
			linkRecommendationQualityGates = suggestedEditSession.qualityGateConfig[ 'link-recommendation' ] || {},
			linkRecommendationDailyTasksExceeded =
				linkRecommendationQualityGates.dailyLimit || false;
		if ( isImageRecommendationTask ) {
			taskTypesToFetch = [ 'image-recommendation' ];
		} else {
			taskTypesToFetch = preferences.taskTypes;
		}
		if ( ( isImageRecommendationTask && imageRecommendationDailyTasksExceeded ) ||
			( isLinkRecommendationTask && linkRecommendationDailyTasksExceeded ) ) {
			// If user has done an image or link recommendation task and the limit is exceeded,
			// we want to fetch all possible task types. We'll filter out
			// 'image-recommendation' or 'link-recommendation' out later.
			taskTypesToFetch = preferences.taskTypes;
		}

		if ( imageRecommendationDailyTasksExceeded ) {
			// Filter out image-recommendation if the limit is exceeded, whether or not this is a
			// image recommendation task.
			// If this yields an empty array the post edit dialog will just not show a card,
			// which is OK.
			taskTypesToFetch = taskTypesToFetch.filter( function ( taskType ) {
				return taskType !== 'image-recommendation';
			} );
		}
		if ( linkRecommendationDailyTasksExceeded ) {
			// Filter out link-recommendation if the limit is exceeded, whether or not this is a
			// link recommendation task.
			// If this yields an empty array the post edit dialog will just not show a card,
			// which is OK.
			taskTypesToFetch = taskTypesToFetch.filter( function ( taskType ) {
				return taskType !== 'link-recommendation';
			} );
		}
		return api.fetchTasks(
			taskTypesToFetch,
			preferences.topicFilters,
			apiConfig
		).then( function ( data ) {
			data = data || {};
			otherTasks = ( data.tasks || [] ).filter( function ( task ) {
				return task.title !== suggestedEditSession.title.getPrefixedText();
			} );
		} );
	}

	/**
	 * Fetch the next task.
	 *
	 * @return {jQuery.Promise<mw.libs.ge.TaskData|null>} A promise that will resolve to a task
	 *   data object, or fail with an error message if fetching the task failed.
	 */
	function getNextTask() {
		return fetchOtherTasks().then( function () {
			return otherTasks[ 0 ] || null;
		} );
	}

	/**
	 * Fetch additional data for the specified task.
	 *
	 * @param {mw.libs.ge.TaskData|null} task Task data
	 * @return {jQuery.Promise<mw.libs.ge.TaskData>} A promise that resolves with task data or
	 *   rejects if the task data can't be fetched
	 */
	function fetchExtraDataForTask( task ) {
		var extraDataPromise;
		if ( task && !OO.ui.isMobile() ) {
			extraDataPromise = $.when(
				api.getExtraDataFromPcs( task, apiConfig ),
				api.getExtraDataFromAqs( task, apiConfig )
			).then( function () {
				return task;
			} );
		} else if ( task ) {
			task.pageviews = null;
			extraDataPromise = api.getExtraDataFromPcs( task, apiConfig );
		} else {
			extraDataPromise = $.Deferred().reject().promise();
		}
		return extraDataPromise;
	}

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
	 * Update the navigation states for the specified post-edit panel
	 *
	 * @param {PostEditPanel} postEditPanel
	 * @param {number} currentTaskPosition Zero-based index of the current task shown
	 * @param {number} totalTasks Total number of tasks in the queue
	 */
	function updateNavigationStates( postEditPanel, currentTaskPosition, totalTasks ) {
		postEditPanel.togglePrevNavigation( currentTaskPosition !== 0 );
		postEditPanel.toggleNextNavigation( currentTaskPosition < totalTasks - 1 );
		postEditPanel.updatePager( currentTaskPosition + 1, totalTasks );
	}

	/**
	 * Show the previous or next task in the queue in the specified post-edit panel
	 *
	 * @param {PostEditPanel} postEditPanel
	 * @param {boolean} [isPrev] Whether the previous task should be shown
	 */
	function navigateTask( postEditPanel, isPrev ) {
		if ( ( isPrev && nextTaskIndex === 0 ) ||
			( nextTaskIndex === otherTasks.length - 1 && !isPrev ) ) {
			updateNavigationStates( postEditPanel, nextTaskIndex, otherTasks.length );
			return;
		}

		if ( isPrev ) {
			nextTaskIndex -= 1;
		} else {
			nextTaskIndex += 1;
		}

		// show the next task before the metadata is loaded
		postEditPanel.updateNextTask( otherTasks[ nextTaskIndex ] );
		updateNavigationStates( postEditPanel, nextTaskIndex, otherTasks.length );

		fetchExtraDataForTask( otherTasks[ nextTaskIndex ] ).then( function ( updatedTask ) {
			// show metadata for the current task
			postEditPanel.updateTask( updatedTask );
		} );
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
		var postEditPanel, displayPanelPromises, openPromise, extraDataPromise, result,
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
			taskTypes: task ? taskTypes : {},
			newcomerTaskLogger: newcomerTaskLogger,
			helpPanelLogger: helpPanelLogger,
			imageRecommendationDailyTasksExceeded: imageRecommendationDailyTasksExceeded,
			linkRecommendationDailyTasksExceeded: linkRecommendationDailyTasksExceeded
		} );
		postEditPanel.on( 'postedit-prev-task', function () {
			navigateTask( postEditPanel, true );
		} );
		postEditPanel.on( 'postedit-next-task', function () {
			navigateTask( postEditPanel );
		} );
		postEditPanel.updatePager( nextTaskIndex + 1, otherTasks.length );
		updateNavigationStates( postEditPanel, nextTaskIndex, otherTasks.length );

		displayPanelPromises = displayPanel( postEditPanel );
		openPromise = displayPanelPromises.openPromise;
		openPromise.done( postEditPanel.logImpression.bind( postEditPanel, {
			savedTaskType: suggestedEditSession.taskType,
			errorMessage: errorMessage,
			userTaskTypes: preferences.taskTypes,
			userTopics: preferences.topicFilters ? preferences.topicFilters.getTopics() : [],
			newcomerTaskToken: suggestedEditSession.newcomerTaskToken
		} ) );

		extraDataPromise = fetchExtraDataForTask( task );
		extraDataPromise.then( function ( updateTask ) {
			postEditPanel.updateTask( updateTask );
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
		GrowthTasksApi: GrowthTasksApi,

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
			return getNextTask().then( function ( task ) {
				return setup( task, null, isDialogShownUponReload );
			}, function ( errorMessage ) {
				return setup( null, errorMessage );
			} );
		}

	};
}() );
