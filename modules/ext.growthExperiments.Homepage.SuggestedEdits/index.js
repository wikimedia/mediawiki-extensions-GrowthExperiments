( function () {
	var EditCardWidget = require( './EditCardWidget.js' ),
		ErrorCardWidget = require( './ErrorCardWidget.js' ),
		NoResultsWidget = require( './NoResultsWidget.js' ),
		GrowthTasksApi = require( './GrowthTasksApi.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		SuggestedEditsModule = require( './SuggestedEditsModule.js' ),
		TaskTypesAbFilter = require( './TaskTypesAbFilter.js' ),
		aqsConfig = require( './AQSConfig.json' ),
		StartEditing = require( './StartEditing.js' ),
		taskTypes = TaskTypesAbFilter.getTaskTypes(),
		defaultTaskTypes = TaskTypesAbFilter.getDefaultTaskTypes(),
		suggestedEditsModule;

	/**
	 * Set up the suggested edits module within the given container, fetch the tasks
	 * and display the first.
	 *
	 * @param {jQuery} $container
	 * @return {jQuery.Promise} Status promise.
	 */
	function initSuggestedTasks( $container ) {
		var initTime = mw.now(),
			api = new GrowthTasksApi( {
				taskTypes: taskTypes,
				defaultTaskTypes: defaultTaskTypes,
				suggestedEditsConfig: require( './config.json' ),
				aqsConfig: aqsConfig,
				isMobile: OO.ui.isMobile()
			} ),
			preferences = api.getPreferences(),
			$wrapper = $container.find( '.suggested-edits-module-wrapper' ),
			mode = $wrapper.closest( '.growthexperiments-homepage-module' ).data( 'mode' ),
			taskPreviewData = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ][ 'task-preview' ] || {},
			fetchTasksOptions = {},
			topicMatching = mw.config.get( 'GEHomepageSuggestedEditsEnableTopics' );

		if ( !$wrapper.length ) {
			return;
		}

		if ( OO.ui.isMobile() ) {
			$container.addClass( 'suggested-edits-module-mobile-overlay' );
		}

		suggestedEditsModule = new SuggestedEditsModule(
			{
				$container: $container,
				$element: $wrapper,
				$nav: $container.find( '.suggested-edits-footer-navigation' ),
				taskTypePresets: preferences.taskTypes,
				topicPresets: preferences.topics,
				topicMatching: topicMatching,
				mode: mode,
				qualityGateConfig: taskPreviewData.qualityGateConfig || {}
			},
			new Logger(
				mw.config.get( 'wgGEHomepageLoggingEnabled' ),
				mw.config.get( 'wgGEHomepagePageviewToken' )
			),
			api );

		if ( taskPreviewData.title ) {
			fetchTasksOptions = { firstTask: taskPreviewData };
			suggestedEditsModule.taskQueue.push( taskPreviewData );
			suggestedEditsModule.queuePosition = 0;
		} else if ( taskPreviewData.noresults ) {
			suggestedEditsModule.showCard(
				new NoResultsWidget( { topicMatching: topicMatching } )
			);
		} else if ( taskPreviewData.error ) {
			suggestedEditsModule.showCard( new ErrorCardWidget() );
			mw.log.error( 'task preview data unavailable: ' + taskPreviewData.error );
			mw.errorLogger.logError( new Error( 'task preview data unavailable: ' +
				taskPreviewData.error ), 'error.growthexperiments' );
		} else {
			// This code path shouldn't be possible with our current setup, where tasks
			// are fetched server side and exported from SuggestedEdits.php. But keep it
			// for now, in case that setup changes.
			// Show an empty skeleton card, which will be overwritten once tasks are fetched.
			suggestedEditsModule.showCard( new EditCardWidget( {} ) );
		}
		suggestedEditsModule.updateControls();
		// Track the TTI on client-side.
		mw.track(
			'timing.growthExperiments.specialHomepage.modules.suggestedEditsTimeToInteractive.' +
			( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
			mw.now() - initTime
		);
		// Track the server side render start time (first line in SpecialHomepage#execute()) to
		// TTI on client-side.
		mw.track(
			'timing.growthExperiments.specialHomepage.modules.suggestedEditsTimeToInteractive.serverSideStartInclusive.' +
			( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
			mw.now() - mw.config.get( 'GEHomepageStartTime' )
		);
		return suggestedEditsModule.fetchTasksAndUpdateView( fetchTasksOptions ).then( function () {
			if ( suggestedEditsModule.currentCard ) {
				// currentCard was set by fetchTasksAndUpdateView, do not overwrite it
				if ( fetchTasksOptions.firstTask ) {
					// update task count
					suggestedEditsModule.updateControls();
				}
				return $.Deferred().resolve();
			}
			return suggestedEditsModule.showCard();
		} ).done( function () {
			mw.track(
				'timing.growthExperiments.specialHomepage.modules.suggestedEditsLoadingComplete.' +
					( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
				mw.now() - initTime
			);
			// Use done instead of then because 1) we don't want to make the caller
			// wait for the preload; 2) failed preloads should not result in an
			// error card, as they don't affect the current card. The load will be
			// retried when the user navigates.
			suggestedEditsModule.preloadNextCard();
		} );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode.
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js.
	// Export setup state so the caller can wait for it when setting up the module
	// on the client side.
	// eslint-disable-next-line no-jquery/no-global-selector
	var $suggestedEditsContainer = $( '.growthexperiments-homepage-container' );
	initSuggestedTasks( $suggestedEditsContainer );
	StartEditing.initialize( $suggestedEditsContainer );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'suggested-edits' ) {
			initSuggestedTasks( $content );
		}
	} );

	/**
	 * Subscribe to updateMatchCount events in the StartEditing dialog to update the hidden
	 * Suggested Edits module state with topic/task type selection and result counts. That way,
	 * when the StartEditing dialog is closed, we can unhide the Suggested Edits module
	 * and show an accurate state to the user.
	 *
	 * @param {string[]} taskTypeSelection List of active task type IDs in the task type selector
	 * @param {string[]} topicSelection List of selected topic IDs in the topic selector
	 */
	mw.hook( 'growthexperiments.StartEditingDialog.updateMatchCount' ).add( function ( taskTypeSelection, topicSelection ) {
		if ( suggestedEditsModule ) {
			suggestedEditsModule.filters.taskTypeFiltersDialog.taskTypeSelector
				.setSelected( taskTypeSelection );
			suggestedEditsModule.filters.taskTypeFiltersDialog.savePreferences();
			suggestedEditsModule.filters.topicFiltersDialog.topicSelector
				.setSelectedTopics( topicSelection );
			suggestedEditsModule.filters.topicFiltersDialog.savePreferences();
			suggestedEditsModule.fetchTasksAndUpdateView().then( function () {
				suggestedEditsModule.updateControls();
				suggestedEditsModule.showCard();
			} );
		}
	} );

}() );
