( function () {
	const ErrorCardWidget = require( './ErrorCardWidget.js' ),
		NoResultsWidget = require( './NoResultsWidget.js' ),
		Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		SuggestedEditsModule = require( './SuggestedEditsModule.js' ),
		StartEditing = require( './StartEditing.js' ),
		rootStore = require( 'ext.growthExperiments.DataStore' ),
		TOPIC_MATCH_MODES = rootStore.CONSTANTS.TOPIC_MATCH_MODES,
		tasksStore = rootStore.newcomerTasks,
		filtersStore = rootStore.newcomerTasks.filters;
	let suggestedEditsModule;

	/**
	 * Set up the suggested edits module within the given container.
	 *
	 * @param {jQuery} $container
	 * @return {jQuery.Promise|undefined} Status promise.
	 */
	function initSuggestedTasks( $container ) {
		const initTime = mw.now(),
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
				mw.config.get( 'wgGEHomepagePageviewToken' )
			),
			rootStore
		);

		if ( taskQueue.length && !taskPreviewData.error ) {
			tasksStore.setPreloadedTaskQueue( taskQueue );
		} else if ( taskPreviewData.noresults ) {
			suggestedEditsModule.showCard(
				new NoResultsWidget( {
					topicMatching: filtersStore.topicsEnabled,
					topicMatchModeIsAND: filtersStore.topicsMatchMode === TOPIC_MATCH_MODES.AND,
					setMatchModeOr: suggestedEditsModule.setMatchModeAndSave.bind( suggestedEditsModule, TOPIC_MATCH_MODES.OR )
				} )
			);
		} else if ( taskPreviewData.error ) {
			suggestedEditsModule.showCard( new ErrorCardWidget() );
			mw.log.error( 'task preview data unavailable: ' + taskPreviewData.error );
			mw.errorLogger.logError( new Error( 'task preview data unavailable: ' +
				taskPreviewData.error ), 'error.growthexperiments' );
		}

		suggestedEditsModule.updateControls();

		const clientSideLoadDuration = mw.now() - initTime, // Time since module init
			serverDuration = mw.now() - mw.config.get( 'GEHomepageStartTime' );
		// Track the TTI on client-side.
		mw.track(
			'timing.growthExperiments.specialHomepage.modules.suggestedEditsTimeToInteractive.' +
			( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
			clientSideLoadDuration
		);
		mw.track(
			'stats.mediawiki_GrowthExperiments_suggested_edits_tti_seconds',
			clientSideLoadDuration,
			{
				platform: OO.ui.isMobile() ? 'mobile' : 'desktop',
				// eslint-disable-next-line camelcase
				includes_server_response_time: false
			}
		);

		try {
			// Track the server side render start time (first line in SpecialHomepage#execute()) to
			// TTI on client-side.
			mw.track(
				'timing.growthExperiments.specialHomepage.modules.suggestedEditsTimeToInteractive.serverSideStartInclusive.' +
				( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
				serverDuration
			);
			mw.track(
				'stats.mediawiki_GrowthExperiments_suggested_edits_server_tti_seconds',
				serverDuration,
				{
					platform: OO.ui.isMobile() ? 'mobile' : 'desktop',
					// eslint-disable-next-line camelcase
					includes_server_response_time: true
				}
			);
		} catch ( err ) {
			const geHomepageStartTime = mw.config.get( 'GEHomepageStartTime' );
			// eslint-disable-next-line camelcase
			err.error_context = {
				serverDuration: serverDuration,
				serverDurationType: typeof serverDuration,
				GEHomepageStartTime: geHomepageStartTime,
				GEHomepageStartTimeType: typeof geHomepageStartTime
			};
			mw.errorLogger.logError(
				err,
				'error.growthexperiments'
			);
		}
		return $.Deferred().resolve();
	}

	if ( window.QUnit ) {
		// Let tests control the side-effects.
		return;
	}

	// Try setup for desktop mode and server-side-rendered mobile mode.
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js.
	// Export setup state so the caller can wait for it when setting up the module
	// on the client side.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $suggestedEditsContainer = $( '.growthexperiments-homepage-container' );
	initSuggestedTasks( $suggestedEditsContainer );
	StartEditing.initialize( $suggestedEditsContainer, filtersStore.shouldUseTopicMatchMode );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( ( moduleName, $content ) => {
		if ( moduleName === 'suggested-edits' ) {
			initSuggestedTasks( $content );
		}
	} );
}() );
