'use strict';

( function () {
	var Drawer = mw.mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Drawer : null,
		PostEditPanel = require( './PostEditPanel.js' ),
		PostEditDialog = require( './PostEditDialog.js' ),
		GrowthTasksApi = require( '../homepage/suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		HelpPanelLogger = require( '../ext.growthExperiments.Help/HelpPanelLogger.js' ),
		NewcomerTaskLogger = require( '../homepage/suggestededits/ext.growthExperiments.NewcomerTaskLogger.js' ),
		TaskTypesAbFilter = require( '../homepage/suggestededits/TaskTypesAbFilter.js' ),
		taskTypes = TaskTypesAbFilter.getTaskTypes(),
		defaultTaskTypes = TaskTypesAbFilter.getDefaultTaskTypes(),
		suggestedEditsConfig = require( '../homepage/suggestededits/config.json' ),
		aqsConfig = require( '../homepage/suggestededits/AQSConfig.json' ),
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
			size: 10
		},
		preferences = api.getPreferences(),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		isLinkRecommendationTask = ( suggestedEditSession.taskType === 'link-recommendation' ),
		isImageRecommendationTask = ( suggestedEditSession.taskType === 'image-recommendation' ),
		isStructuredTask = isLinkRecommendationTask || isImageRecommendationTask,
		newcomerTaskLogger = new NewcomerTaskLogger(),
		helpPanelLogger = new HelpPanelLogger( helpConfig.GEHelpPanelLoggingEnabled, {
			context: 'postedit',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} ),
		otherTasks = [],
		nextTaskIndex = 0;

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
		// 10 tasks are hopefully enough to find one that's not protected.
		return api.fetchTasks(
			taskTypesToFetch,
			preferences.topics,
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
	 * Display the given panel, using a mobile or desktop format as appropriate.
	 * Also handles some of the logging.
	 *
	 * @param {PostEditPanel} postEditPanel
	 * @return {Object} An object with:
	 *   - openPromise {jQuery.Promise} A promise that resolves when the dialog has been displayed.
	 *   - closePromise {jQuery.Promise} A promise that resolves when the dialog has been closed.
	 */
	function displayPanel( postEditPanel ) {
		var drawer, dialog, lifecycle, windowManager, openPromise, closePromise;

		if ( OO.ui.isMobile() && Drawer ) {
			closePromise = $.Deferred().resolve();
			drawer = new Drawer( {
				children: [
					postEditPanel.getMainArea()
				].concat(
					postEditPanel.getFooterButtons()
				),
				className: 'mw-ge-help-panel-postedit-drawer',
				onBeforeHide: function () {
					postEditPanel.logClose();
					// There's no onAfterHide hook in Drawer; allow a short delay for
					// the drawer to close before resolving the promise.
					setTimeout( function () {
						closePromise.resolve();
					}, 250 );
				}
			} );
			postEditPanel.on( 'edit-link-clicked', function () {
				drawer.hide();
			} );
			drawer.$el.find( '.drawer' ).prepend(
				$( '<div>' )
					.addClass( 'mw-ge-help-panel-postedit-message-anchor' )
					.append( postEditPanel.getSuccessMessage().$element )
			);

			document.body.appendChild( drawer.$el[ 0 ] );
			openPromise = drawer.show();
		} else {
			dialog = new PostEditDialog( { panel: postEditPanel } );
			windowManager = new OO.ui.WindowManager();
			$( document.body ).append( windowManager.$element );
			windowManager.addWindows( [ dialog ] );
			lifecycle = windowManager.openWindow( dialog );
			closePromise = lifecycle.closed.done( function () {
				// Used by GettingStarted extension.
				mw.hook( 'postEdit.afterRemoval' ).fire();
				postEditPanel.logClose();
			} );
			lifecycle.opened.then( function () {
				// Close dialog on outside click.
				dialog.$element.on( 'click', function ( e ) {
					if ( e.target === dialog.$element[ 0 ] ) {
						windowManager.closeWindow( dialog );
					}
				} );
			} );
			postEditPanel.on( 'edit-link-clicked', function () {
				dialog.close();
			} );
			openPromise = lifecycle.opened;
		}

		postEditPanel.on( 'refresh-button-clicked', function () {
			if ( nextTaskIndex === otherTasks.length - 1 ) {
				nextTaskIndex = 0;
			} else {
				nextTaskIndex += 1;
			}
			fetchExtraDataForTask( otherTasks[ nextTaskIndex ] ).then( function ( updatedTask ) {
				postEditPanel.updateNextTask( updatedTask );
			} );
		} );

		return {
			openPromise: openPromise,
			closePromise: closePromise
		};
	}

	/**
	 * Helper method to tie getNextTask() and displayPanel() together.
	 *
	 * @param {mw.libs.ge.TaskData|null} task Task data, or null when the task card should not be
	 *   shown.
	 * @param {string|null} errorMessage Error message, or null when there was no error.
	 * @return {Object} An object with:
	 *   - task: task data as a plain Object (as returned by GrowthTasksApi), omitted
	 *     when loading the task failed and when the task parameter is null;
	 *   - errorMessage: error message (only when loading the task failed);
	 *   - panel: the PostEditPanel object;
	 *   - openPromise: a promise that resolves when the panel has been displayed.
	 *   - closePromise: A promise that resolves when the dialog has been closed.
	 */
	function setup( task, errorMessage ) {
		var postEditPanel, displayPanelPromises, openPromise, closePromise, extraDataPromise, result,
			imageRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'image-recommendation' ] || {},
			imageRecommendationDailyTasksExceeded =
				imageRecommendationQualityGates.dailyLimit || false,
			linkRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'link-recommendation' ] || {},
			linkRecommendationDailyTasksExceeded =
				linkRecommendationQualityGates.dailyLimit || false;

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
		displayPanelPromises = displayPanel( postEditPanel );
		openPromise = displayPanelPromises.openPromise;
		openPromise.done( postEditPanel.logImpression.bind( postEditPanel, {
			savedTaskType: suggestedEditSession.taskType,
			errorMessage: errorMessage,
			userTaskTypes: preferences.taskTypes,
			userTopics: preferences.topics
		} ) );

		closePromise = displayPanelPromises.closePromise;
		closePromise.done( function () {
			if ( isStructuredTask && suggestedEditSession.taskState !== 'cancelled' ) {
				// Structured tasks are different from the unstructured tasks in that they
				// cannot be repeated immediately after edit. So, after post edit dialog is closed,
				// clear out the task type ID and task data. Currently not used elsewhere,
				// but in case some code is relying on it, better to have removed here.
				suggestedEditSession.taskType = null;
				suggestedEditSession.taskData = null;
				suggestedEditSession.save();
				if ( !OO.ui.isMobile() ) {
					// On mobile, the page is reloaded automatically after making an edit.
					// On desktop, a reload is needed to unload StructuredTaskArticleTarget.
					// Reloading the window is kind of extreme but on the other hand
					// canceling out of the post edit dialog isn't a path we are trying to
					// optimize for.
					var uri = new mw.Uri();
					delete uri.query.gesuggestededit;
					window.location.href = uri.toString();
				}
			}
		} );

		extraDataPromise = fetchExtraDataForTask( task );
		extraDataPromise.then( function ( updateTask ) {
			postEditPanel.updateTask( updateTask );
		} );

		result = {
			panel: postEditPanel,
			openPromise: openPromise,
			closePromise: closePromise
		};
		if ( task ) {
			result.task = task;
		} else if ( errorMessage ) {
			result.errorMessage = errorMessage;
		}
		return result;
	}

	module.exports = {
		PostEditDialog: PostEditDialog,
		GrowthTasksApi: GrowthTasksApi,

		/**
		 * Create and show the panel (a dialog or a drawer, depending on the current device).
		 *
		 * @return {jQuery.Promise<Object>} A promise resolving to an object with:
		 *   - task: task data as a plain Object (as returned by GrowthTasksApi), might be omitted
		 *     when loading the task failed;
		 *   - errorMessage: error message (only when loading the task failed);
		 *   - panel: the PostEditPanel object;
		 *   - openPromise: a promise that resolves when the panel has been displayed.
		 *   - closePromise: A promise that resolves when the dialog has been closed.
		 */
		setupPanel: function () {
			return getNextTask().then( function ( task ) {
				return setup( task, null );
			}, function ( errorMessage ) {
				return setup( null, errorMessage );
			} );
		}

	};
}() );
