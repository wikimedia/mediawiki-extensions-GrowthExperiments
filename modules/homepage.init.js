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
				'stats.mediawiki_GrowthExperiments_navigation_duration_seconds',
				navigationEntries[ 0 ].duration,
				{
					// eslint-disable-next-line camelcase
					navigation_type: navigationEntries[ 0 ].type,
					wiki: mw.config.get( 'wgDBname' )
				}
			);

			const sizeBytes = navigationEntries[ 0 ].transferSize;
			mw.track(
				// Using 'timing' for transfer size sounds conceptually wrong, but we want
				// the various features that statsd timing gives us (see
				// https://github.com/statsd/statsd/blob/master/docs/metric_types.md)
				'timing.growthExperiments.specialHomepage.navigationTransferSize',
				sizeBytes
			);

			const buckets = [ 8, 16, 32, 64, 128 ]
				.filter( ( ceil ) => ( sizeBytes / 1024 ) <= ceil )
				.map( ( ceil ) => ceil + '_KiB' )
				.concat( 'all' );
			for ( const bucket of buckets ) {
				mw.track(
					'stats.mediawiki_GrowthExperiments_homepage_transfersize_bytes_total',
					1,
					{ bucket }
				);
			}
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
