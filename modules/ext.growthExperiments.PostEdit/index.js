'use strict';

( function () {
	const PostEditDrawer = require( './PostEditDrawer.js' ),
		PostEditPanel = require( './PostEditPanel.js' ),
		TryNewTaskPanel = require( './TryNewTaskPanel.js' ),
		HelpPanelLogger = require( '../utils/HelpPanelLogger.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
		suggestedEditSession = SuggestedEditSession.getInstance(),
		newcomerTaskLogger = new NewcomerTaskLogger(),
		postEditPanelHelpPanelLogger = new HelpPanelLogger( {
			context: 'postedit',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} ),
		tryNewTaskHelpPanelLogger = new HelpPanelLogger( {
			context: 'postedit-trynewtask',
			previousEditorInterface: suggestedEditSession.editorInterface,
			sessionId: suggestedEditSession.clickId,
			isSuggestedTask: suggestedEditSession.active
		} ),
		rootStore = require( 'ext.growthExperiments.DataStore' ),
		CONSTANTS = rootStore.CONSTANTS,
		ALL_TASK_TYPES = CONSTANTS.ALL_TASK_TYPES,
		tasksStore = rootStore.newcomerTasks,
		filtersStore = tasksStore.filters;

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
		let suppressClose = false;
		const drawer = new PostEditDrawer( postEditPanel, logger );
		const closeDrawer = function () {
			if ( !suppressClose ) {
				drawer.close();
			}
		};

		if ( mw.libs.ge && mw.libs.ge.HelpPanel ) {
			// It doesn't make any sense to show the help panel in an open state alongside
			// the post-edit panel or the try new task panel, so close it.
			mw.libs.ge.HelpPanel.close();
		}

		$( document.body ).append( drawer.$element );
		if ( showToast ) {
			drawer.showWithToastMessage();
		} else {
			drawer.openWithIntroContent();
		}
		drawer.opened.then( () => {
			// Hide the drawer if the user opens the editor again.
			// HACK ignore memorized previous ve.activationComplete events.
			suppressClose = true;
			if ( OO.ui.isMobile() ) {
				mw.hook( 'mobileFrontend.editorOpened' ).add( closeDrawer );
			} else {
				mw.hook( 've.activationComplete' ).add( closeDrawer );
				mw.hook( 'wikipage.editform' ).add( closeDrawer );
			}
			suppressClose = false;
		} );
		drawer.closed.then( () => {
			if ( OO.ui.isMobile() ) {
				mw.hook( 'mobileFrontend.editorOpened' ).remove( closeDrawer );
			} else {
				mw.hook( 've.activationComplete' ).remove( closeDrawer );
				mw.hook( 'wikipage.editform' ).remove( closeDrawer );
			}
			postEditPanel.logClose();
		} );
		return {
			openPromise: drawer.opened,
			closePromise: drawer.closed
		};
	}

	/**
	 * Helper method to tie getNextTask() and displayPanel() together.
	 *
	 * @param {boolean} showToast Whether the panel should show its predefined toast message when opening.
	 * @return {Object} An object with:
	 *   - task: task data as a plain Object (as returned by GrowthTasksApi), omitted
	 *     when loading the task failed and when the task parameter is null;
	 *   - errorMessage: error message (only when loading the task failed);
	 *   - panel: the mw.libs.ge.PostEditPanel object;
	 *   - openPromise: a promise that resolves when the panel has been displayed.
	 *   - closePromise: A promise that resolves when the dialog has been closed.
	 */
	function setup( showToast ) {
		const imageRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'image-recommendation' ] || {},
			imageRecommendationDailyTasksExceeded =
				imageRecommendationQualityGates.dailyLimit || false,
			sectionImageRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'section-image-recommendation' ] || {},
			sectionImageRecommendationDailyTasksExceeded =
				sectionImageRecommendationQualityGates.dailyLimit || false,
			linkRecommendationQualityGates =
				suggestedEditSession.qualityGateConfig[ 'link-recommendation' ] || {},
			linkRecommendationDailyTasksExceeded =
				linkRecommendationQualityGates.dailyLimit || false;

		const postEditPanel = new PostEditPanel( {
			taskType: suggestedEditSession.taskType,
			taskState: suggestedEditSession.taskState,
			taskTypes: ALL_TASK_TYPES,
			newcomerTasksStore: tasksStore,
			newcomerTaskLogger: newcomerTaskLogger,
			helpPanelLogger: postEditPanelHelpPanelLogger,
			imageRecommendationDailyTasksExceeded: imageRecommendationDailyTasksExceeded,
			sectionImageRecommendationDailyTasksExceeded: sectionImageRecommendationDailyTasksExceeded,
			linkRecommendationDailyTasksExceeded: linkRecommendationDailyTasksExceeded
		} );

		const displayPanelPromises = displayPanel( postEditPanel, postEditPanelHelpPanelLogger, showToast );

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
		setupPanel: function ( nextSuggestedTaskType, showToast ) {
			const fetchTasksConfig = {
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

			let setupResult = null;
			tasksStore.fetchTasks( 'postEditDialog', fetchTasksConfig ).catch( ( errorMessage ) => {
				if ( errorMessage ) {
					mw.log.error( errorMessage );
					mw.errorLogger.logError( new Error( errorMessage ), 'error.growthexperiments' );
				}
				setupResult.openPromise.then( logPanelImpression( setupResult.panel, errorMessage ) );
			} );
			setupResult = setup( showToast );
			setupResult.openPromise.then( logPanelImpression( setupResult.panel ) );
			return setupResult;
		},
		/**
		 * Create and maybe show the try-new-task dialog panel.
		 *
		 * @return {jQuery.Promise<{accepted: boolean, shown: boolean, closeData: undefined|null|string}>} A promise with
		 *  three fields:
		 *   - shown: whether the panel was shown
		 *   - accepted: whether the panel was shown and accepted by the user
		 *   - closeData: the value which the close handler was called with:
		 *     - null: the panel was rejected by the user
		 *     - string: the panel was accepted by the user. The returned string represents
		 *  a valid task type id with the next suggested task type.
		 *     - undefined: the panel was closed without any data, probably by the close handler in displayPanel. This
		 * can happen when the user clicks on the VE "Edit" link while the panel is open
		 */
		setupTryNewTaskPanel: function () {
			const tryNewTaskOptOuts = mw.config.get( 'wgGELevelingUpTryNewTaskOptOuts', [] );
			if ( SuggestedEditSession.static.shouldShowLevelingUpFeatures() &&
				// A next suggested task type is available for the user
				suggestedEditSession.nextSuggestedTaskType &&
				// The user hasn't opted out of seeing the prompt for this task type
				!tryNewTaskOptOuts.includes( suggestedEditSession.taskType )
			) {
				const tryNewTaskPanel = new TryNewTaskPanel( {
					nextSuggestedTaskType: suggestedEditSession.nextSuggestedTaskType,
					activeTaskType: suggestedEditSession.taskType,
					helpPanelLogger: tryNewTaskHelpPanelLogger,
					tryNewTaskOptOuts: tryNewTaskOptOuts
				} );
				const displayPanelPromises = displayPanel( tryNewTaskPanel, tryNewTaskHelpPanelLogger, true );
				displayPanelPromises.openPromise.then( () => {
					tryNewTaskPanel.logImpression( {
						// Increment the count for the task type, because the try new task panel
						// is triggered at GELevelingUpManagerTaskTypeCountThresholdMultiple - 1,
						// so when this code runs, the suggestedEditSession's edit count reflects
						// the edit count just before the edit was saved.
						'edit-count-for-task-type': suggestedEditSession.editCountByTaskType[ suggestedEditSession.taskType ] + 1
					} );
				} );
				return displayPanelPromises.closePromise.then( ( closeData ) => ( {
					accepted: typeof closeData === 'string',
					closeData: closeData,
					shown: true
				} ) );
			}

			return $.Deferred().resolve( {
				accepted: false,
				shown: false
			} );
		}
	};
}() );
