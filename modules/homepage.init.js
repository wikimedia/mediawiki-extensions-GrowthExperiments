( function () {
	if ( 'impact' in mw.config.get( 'homepagemodules' ) ) {
		require( './ext.growthExperiments.Homepage.Impact/index.js' );
	}

	// Log impressions and clicks for each homepage module
	require( './ext.growthExperiments.Homepage.Logging/index.js' );

	if ( mw.config.get( 'shouldShowConfirmEmailNotice' ) ) {
		require( './ext.growthExperiments.Homepage.ConfirmEmailNotice/index.js' );
	}

}() );
