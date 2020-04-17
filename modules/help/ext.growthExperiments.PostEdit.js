'use strict';

( function () {
	var Drawer = mw.mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Drawer : null,
		PostEditPanel = require( './ext.growthExperiments.PostEditPanel.js' ),
		PostEditDialog = require( './ext.growthExperiments.PostEditDialog.js' ),
		GrowthTasksApi = require( '../homepage/suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		taskTypes = require( '../homepage/suggestededits/TaskTypes.json' ),
		suggestedEditsConfig = require( '../homepage/suggestededits/config.json' ),
		aqsConfig = require( '../homepage/suggestededits/AQSConfig.json' );

	/**
	 * Fetch the next task.
	 * @return {jQuery.Promise<Object|null>} A promise that will resolve to a task data object,
	 *   or null if fetching the task failed. The promise itself never fails.
	 */
	function getNextTask() {
		var api, preferences;

		api = new GrowthTasksApi( {
			taskTypes: taskTypes,
			aqsConfig: aqsConfig,
			suggestedEditsConfig: suggestedEditsConfig
		} );
		preferences = api.getPreferences();

		// 10 tasks are hopefully enough to find one that's not protected.
		return api.fetchTasks(
			preferences.taskTypes,
			preferences.topics,
			{ getDescription: true, size: 10 }
		).then( function ( data ) {
			var task = data.tasks[ 0 ] || null;
			if ( task && !OO.ui.isMobile() ) {
				return $.when(
					api.getExtraDataFromPcs( task ),
					api.getExtraDataFromAqs( task )
				).then( function () {
					return task;
				} );
			} else if ( task ) {
				return api.getExtraDataFromPcs( task );
			} else {
				return task;
			}
		}, function ( errorMessage ) {
			mw.log.error( errorMessage );
			return null;
		} );
	}

	/**
	 * Display the given panel, using a mobile or desktop format as appropriate.
	 * @param {PostEditPanel} postEditPanel
	 * @return {jQuery.Promise} A promise that resolves when the dialog has been displayed.
	 */
	function displayPanel( postEditPanel ) {
		var drawer, dialog, lifecycle, windowManager;

		if ( OO.ui.isMobile() && Drawer ) {
			drawer = new Drawer( {
				children: [
					postEditPanel.getMainArea()
				].concat(
					postEditPanel.getFooterButtons()
				),
				className: 'mw-ge-help-panel-postedit-drawer'
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
			return drawer.show();
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
			return lifecycle.opened;
		}
	}

	module.exports = {
		PostEditDialog: PostEditDialog,
		GrowthTasksApi: GrowthTasksApi,

		/**
		 * Create and show the panel (a dialog or a drawer, depending on the current device).
		 * @return {jQuery.Promise<Object>} A promise resolving to an object with:
		 *   - task: task data as a plain Object (as returned by GrowthTasksApi);
		 *   - panel: the PostEditPanel object;
		 *   - openPromise: a promise that resolves when the panel has been displayed.
		 */
		setupPanel: function () {
			return getNextTask().then( function ( task ) {
				var postEditPanel = new PostEditPanel( { nextTask: task, taskTypes: taskTypes } ),
					openPromise = displayPanel( postEditPanel );

				return {
					task: task,
					panel: postEditPanel,
					openPromise: openPromise
				};
			} );
		},

		/**
		 * Create and show a reduced version of the panel, without any task suggestion.
		 * @return {Object} A promise resolving to an object with:
		 *   - panel: the PostEditPanel object;
		 *   - openPromise: a promise that resolves when the panel has been displayed.
		 */
		setupPanelWithoutTask: function () {
			var postEditPanel = new PostEditPanel( { nextTask: null, taskTypes: {} } ),
				openPromise = displayPanel( postEditPanel );

			return {
				panel: postEditPanel,
				openPromise: openPromise
			};
		}
	};
}() );
