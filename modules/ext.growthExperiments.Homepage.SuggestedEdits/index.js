( function () {
	var EditCardWidget = require( './EditCardWidget.js' ),
		ErrorCardWidget = require( './ErrorCardWidget.js' ),
		NoResultsWidget = require( './NoResultsWidget.js' ),
		Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		SuggestedEditsModule = require( './SuggestedEditsModule.js' ),
		StartEditing = require( './StartEditing.js' ),
		rootStore = require( 'ext.growthExperiments.DataStore' ),
		tasksStore = rootStore.newcomerTasks,
		filtersStore = rootStore.newcomerTasks.filters,
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
			$wrapper = $container.find( '.suggested-edits-module-wrapper' ),
			mode = $wrapper.closest( '.growthexperiments-homepage-module' ).data( 'mode' ),
			taskPreviewData = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ][ 'task-preview' ] || {};

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
				mode: mode,
				qualityGateConfig: taskPreviewData.qualityGateConfig || {}
			},
			new Logger(
				mw.config.get( 'wgGEHomepageLoggingEnabled' ),
				mw.config.get( 'wgGEHomepagePageviewToken' )
			),
			rootStore
		);

		if ( taskPreviewData.title ) {
			tasksStore.setPreloadedFirstTask( taskPreviewData );

		} else if ( taskPreviewData.noresults ) {
			suggestedEditsModule.showCard(
				new NoResultsWidget( { topicMatching: filtersStore.topicsEnabled } )
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
		return suggestedEditsModule.fetchTasksAndUpdateView().done( function () {
			mw.track(
				'timing.growthExperiments.specialHomepage.modules.suggestedEditsLoadingComplete.' +
					( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
				mw.now() - initTime
			);
		} );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode.
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js.
	// Export setup state so the caller can wait for it when setting up the module
	// on the client side.
	// eslint-disable-next-line no-jquery/no-global-selector
	var $suggestedEditsContainer = $( '.growthexperiments-homepage-container' );
	initSuggestedTasks( $suggestedEditsContainer );
	StartEditing.initialize( $suggestedEditsContainer, filtersStore.shouldUseTopicMatchMode );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'suggested-edits' ) {
			initSuggestedTasks( $content );
		}
	} );
}() );
