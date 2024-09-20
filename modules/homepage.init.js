( function () {
	if ( 'impact' in mw.config.get( 'homepagemodules' ) ) {
		require( './ext.growthExperiments.Homepage.Impact/index.js' );
	}

	if ( 'welcomesurveyreminder' in mw.config.get( 'homepagemodules' ) ) {
		require( './ext.growthExperiments.Homepage.WelcomeSurveyReminder/index.js' );
	}

	// Log impressions and clicks for each homepage module
	require( './ext.growthExperiments.Homepage.Logging/index.js' );

	if ( mw.config.get( 'shouldShowConfirmEmailNotice' ) ) {
		require( './ext.growthExperiments.Homepage.ConfirmEmailNotice/index.js' );
	}

	// Performance instrumentation for Special:Homepage:
	// - navigation duration
	// - navigation transfer size
	// - first paint
	if ( window.performance && window.performance.getEntriesByType ) {
		const navigationEntries = window.performance.getEntriesByType( 'navigation' ),
			performanceEntries = window.performance.getEntries().filter( ( entry ) => entry.name === 'first-contentful-paint' );
		if ( navigationEntries.length ) {
			mw.track(
				'timing.growthExperiments.specialHomepage.navigationDuration',
				navigationEntries[ 0 ].duration
			);
			mw.track(
				// Using 'timing' for transfer size sounds conceptually wrong, but we want
				// the various features that statsd timing gives us (see
				// https://github.com/statsd/statsd/blob/master/docs/metric_types.md)
				'timing.growthExperiments.specialHomepage.navigationTransferSize',
				navigationEntries[ 0 ].transferSize
			);
		}
		if ( performanceEntries.length ) {
			mw.track(
				'timing.growthExperiments.specialHomepage.paintStartTime',
				performanceEntries[ 0 ].startTime
			);
		}
	}

}() );
