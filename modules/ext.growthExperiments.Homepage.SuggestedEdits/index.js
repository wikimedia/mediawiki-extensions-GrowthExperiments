( function () {
	var ErrorCardWidget = require( './ErrorCardWidget.js' ),
		NoResultsWidget = require( './NoResultsWidget.js' ),
		Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		SuggestedEditsModule = require( './SuggestedEditsModule.js' ),
		StartEditing = require( './StartEditing.js' ),
		rootStore = require( 'ext.growthExperiments.DataStore' ),
		tasksStore = rootStore.newcomerTasks,
		filtersStore = rootStore.newcomerTasks.filters,
		suggestedEditsModule;

	/**
	 * Set up the suggested edits module within the given container.
	 *
	 * @param {jQuery} $container
	 * @return {jQuery.Promise} Status promise.
	 */
	function initSuggestedTasks( $container ) {
		var initTime = mw.now(),
			$wrapper = $container.find( '.suggested-edits-module-wrapper' ),
			mode = $wrapper.closest( '.growthexperiments-homepage-module' ).data( 'mode' ),
			suggestedEditsData = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ] || {},
			taskPreviewData = suggestedEditsData[ 'task-preview' ] || {},
			taskQueue = suggestedEditsData[ 'task-queue' ] || [];

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

		if ( taskQueue.length && !taskPreviewData.error ) {
			tasksStore.setTaskQueue( taskQueue );
		} else if ( taskPreviewData.noresults ) {
			suggestedEditsModule.showCard(
				new NoResultsWidget( { topicMatching: filtersStore.topicsEnabled } )
			);
		} else if ( taskPreviewData.error ) {
			suggestedEditsModule.showCard( new ErrorCardWidget() );
			mw.log.error( 'task preview data unavailable: ' + taskPreviewData.error );
			mw.errorLogger.logError( new Error( 'task preview data unavailable: ' +
				taskPreviewData.error ), 'error.growthexperiments' );
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
		// FIXME: suggestedEditsLoadingComplete could probably be removed as it is now a duplicate of
		// suggestedEditsTimeToInteractive. We can leave it for now in case we decide to rollback this change
		// or make other adjustments in loading behavior.
		mw.track(
			'timing.growthExperiments.specialHomepage.modules.suggestedEditsLoadingComplete.' +
			( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
			mw.now() - initTime
		);
		return $.Deferred().resolve();
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
