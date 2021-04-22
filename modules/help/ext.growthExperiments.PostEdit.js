/**
 * @external PostEditPanel
 */
'use strict';

( function () {
	var Drawer = mw.mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Drawer : null,
		PostEditPanel = require( './ext.growthExperiments.PostEditPanel.js' ),
		PostEditDialog = require( './ext.growthExperiments.PostEditDialog.js' ),
		GrowthTasksApi = require( '../homepage/suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		HelpPanelLogger = require( './ext.growthExperiments.HelpPanelLogger.js' ),
		NewcomerTaskLogger = require( '../homepage/suggestededits/ext.growthExperiments.NewcomerTaskLogger.js' ),
		TaskTypesAbFilter = require( '../homepage/suggestededits/TaskTypesAbFilter.js' ),
		taskTypes = TaskTypesAbFilter.filterTaskTypes( require( '../homepage/suggestededits/TaskTypes.json' ) ),
		defaultTaskTypes = TaskTypesAbFilter.filterDefaultTaskTypes(
			require( '../homepage/suggestededits/DefaultTaskTypes.json' ) ),
		suggestedEditsConfig = require( '../homepage/suggestededits/config.json' ),
		aqsConfig = require( '../homepage/suggestededits/AQSConfig.json' ),
		helpConfig = require( './data.json' ),
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
		newcomerTaskLogger = new NewcomerTaskLogger(),
		helpPanelLogger = new HelpPanelLogger( helpConfig.GEHelpPanelLoggingEnabled, {
			context: 'postedit',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} );

	/**
	 * Fetch the next task.
	 *
	 * @return {jQuery.Promise<mw.libs.ge.TaskData|null>} A promise that will resolve to a task data object,
	 *   or fail with an error message if fetching the task failed.
	 */
	function getNextTask() {
		// 10 tasks are hopefully enough to find one that's not protected.
		return api.fetchTasks(
			preferences.taskTypes,
			preferences.topics,
			apiConfig
		).then( function ( data ) {
			var task = data.tasks[ 0 ] || null;
			if ( task && task.title === suggestedEditSession.title.getPrefixedText() ) {
				// Don't offer the same task again.
				task = data.tasks[ 1 ] || null;
			}
			return task;
		} );
	}

	/**
	 * Display the given panel, using a mobile or desktop format as appropriate.
	 * Also handles some of the logging.
	 *
	 * @param {PostEditPanel} postEditPanel
	 * @return {jQuery.Promise} A promise that resolves when the dialog has been displayed.
	 */
	function displayPanel( postEditPanel ) {
		var drawer, dialog, lifecycle, windowManager, openPromise;

		if ( OO.ui.isMobile() && Drawer ) {
			drawer = new Drawer( {
				children: [
					postEditPanel.getMainArea()
				].concat(
					postEditPanel.getFooterButtons()
				),
				className: 'mw-ge-help-panel-postedit-drawer',
				onBeforeHide: function () {
					postEditPanel.logClose();
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
			lifecycle.closing.done( function () {
				postEditPanel.logClose();
				mw.hook( 'postEdit.afterRemoval' ).fire();
			} );
			openPromise = lifecycle.opened;
		}
		return openPromise;
	}

	/**
	 * Helper method to tie getNextTask() and displayPanel() together.
	 *
	 * @param {mw.libs.ge.TaskData|null} task Task data, or null when the task card should not be shown.
	 * @param {string|null} errorMessage Error message, or null when there was no error.
	 * @return {Object} An object with:
	 *   - task: task data as a plain Object (as returned by GrowthTasksApi), omitted
	 *     when loading the task failed and when the task parameter is null;
	 *   - errorMessage: error message (only when loading the task failed);
	 *   - panel: the PostEditPanel object;
	 *   - openPromise: a promise that resolves when the panel has been displayed.
	 */
	function setup( task, errorMessage ) {
		var postEditPanel, openPromise, extraDataPromise, result;

		if ( errorMessage ) {
			mw.log.error( errorMessage );
			mw.errorLogger.logError( new Error( errorMessage ) );
		}

		postEditPanel = new PostEditPanel( {
			nextTask: task,
			taskTypes: task ? taskTypes : {},
			newcomerTaskLogger: newcomerTaskLogger,
			helpPanelLogger: helpPanelLogger
		} );
		openPromise = displayPanel( postEditPanel );
		openPromise.done( postEditPanel.logImpression.bind( postEditPanel, {
			savedTaskType: suggestedEditSession.taskType,
			errorMessage: errorMessage,
			userTaskTypes: preferences.taskTypes,
			userTopics: preferences.topics
		} ) );

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
		extraDataPromise.then( function ( updateTask ) {
			postEditPanel.updateTask( updateTask );
		} );

		result = {
			panel: postEditPanel,
			openPromise: openPromise
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
