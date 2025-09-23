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
				'stats.mediawiki_GrowthExperiments_homepage_loadeventend_seconds',
				navigationEntries[ 0 ].loadEventEnd,
				{
					// eslint-disable-next-line camelcase
					navigation_type: navigationEntries[ 0 ].type,
					wiki: mw.config.get( 'wgDBname' ),
				},
			);

			const sizeBytes = navigationEntries[ 0 ].transferSize;

			const buckets = [ 8, 16, 32, 64, 128 ]
				.filter( ( ceil ) => ( sizeBytes / 1024 ) <= ceil )
				.map( ( ceil ) => ceil + '_KiB' )
				.concat( 'all' );
			for ( const bucket of buckets ) {
				mw.track(
					'stats.mediawiki_GrowthExperiments_homepage_transfersize_bytes_total',
					1,
					{ bucket },
				);
			}
		}
		if ( performanceEntries.length ) {
			mw.track(
				'stats.mediawiki_GrowthExperiments_paint_start_seconds',
				performanceEntries[ 0 ].startTime,
				{
					platform: OO.ui.isMobile() ? 'mobile' : 'desktop',
				},
			);
		}
	}

}() );
