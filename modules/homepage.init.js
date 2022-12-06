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
		var navigationEntry = window.performance.getEntriesByType( 'navigation' )[ 0 ],
			paintEntry = window.performance.getEntriesByType( 'paint' )[ 0 ];
		mw.track(
			'timing.growthExperiments.specialHomepage.navigationDuration',
			navigationEntry.duration
		);
		mw.track(
			// Using 'timing' for transfer size sounds conceptually wrong, but we want
			// the various features that statsd timing gives us (see
			// https://github.com/statsd/statsd/blob/master/docs/metric_types.md)
			'timing.growthExperiments.specialHomepage.navigationTransferSize',
			navigationEntry.transferSize
		);
		mw.track(
			'timing.growthExperiments.specialHomepage.paintStartTime',
			paintEntry.startTime
		);
	}

}() );
