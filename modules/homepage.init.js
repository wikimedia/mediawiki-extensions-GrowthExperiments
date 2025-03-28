( function () {
	if ( 'welcomesurveyreminder' in mw.config.get( 'homepagemodules' ) ) {
		require( './ext.growthExperiments.Homepage.WelcomeSurveyReminder/index.js' );
	}

	// Log impressions and clicks for each homepage module
	require( './ext.growthExperiments.Homepage.Logging/index.js' );

	if ( mw.config.get( 'shouldShowConfirmEmailNotice' ) ) {
		require( './ext.growthExperiments.Homepage.ConfirmEmailNotice/index.js' );
	}

	// Performance instrumentation for Special:Homepage:
	// - page load time (loadEventEnd from Navigation Timing)
	// - download size (transferSize, from Resource Timing)
	// - render time (first-contentful-paint, from Paint Timing)
	if ( window.performance && window.performance.getEntriesByType ) {
		const navigationEntries = window.performance.getEntriesByType( 'navigation' );
		const performanceEntries = window.performance.getEntriesByType( 'paint' )
			.filter( ( entry ) => entry.name === 'first-contentful-paint' );

		if ( navigationEntries[ 0 ] ) {
			mw.track(
				'timing.growthExperiments.specialHomepage.navigationDuration',
				navigationEntries[ 0 ].loadEventEnd
			);
			mw.track(
				'stats.mediawiki_GrowthExperiments_homepage_loadeventend_seconds',
				navigationEntries[ 0 ].loadEventEnd,
				{
					// eslint-disable-next-line camelcase
					navigation_type: navigationEntries[ 0 ].type,
					wiki: mw.config.get( 'wgDBname' )
				}
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
			mw.track(
				'stats.mediawiki_GrowthExperiments_paint_start_seconds',
				performanceEntries[ 0 ].startTime,
				{
					platform: OO.ui.isMobile() ? 'mobile' : 'desktop'
				}
			);
		}
	}

}() );
