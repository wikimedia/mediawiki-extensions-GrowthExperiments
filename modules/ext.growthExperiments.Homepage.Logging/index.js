( function () {
	var Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		// eslint-disable-next-line no-jquery/no-global-selector
		$modules = $( '.growthexperiments-homepage-container .growthexperiments-homepage-module' ),
		handleClick = function ( e ) {
			var $link = $( this ),
				$module = $link.closest( '.growthexperiments-homepage-module' ),
				linkId = $link.data( 'link-id' ) ||
					$link.closest( '[data-link-group-id]' ).data( 'link-group-id' ),
				linkData = $link.data( 'link-data' ),
				moduleName = $module.data( 'module-name' ),
				mode = $module.data( 'mode' ),
				extraData = { linkId: linkId };
			if ( linkData !== undefined && linkData !== null ) {
				extraData.linkData = linkData;
			}
			logger.log( moduleName, mode, 'link-click', extraData );

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			var $module = $( this ),
				moduleName = $module.data( 'module-name' ),
				mode = $module.data( 'mode' );
			logger.log( moduleName, mode, 'impression' );
		},
		uri = new mw.Uri(),
		// Matches routes like /homepage/moduleName or /homepage/moduleName/action
		routeMatches = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/.exec( uri.fragment ),
		routeMatchesModule = routeMatches && $modules.filter( function () {
			return $( this ).data( 'module-name' ) === routeMatches[ 1 ];
		} ).length > 0;

	$modules
		.on( 'click', '[data-link-id], [data-link-group-id] a', handleClick );

	// If we're on mobile and the initial URI specifies a module to navigate to, let
	// ext.growthExperiments.Homepage.mobile/index.js log the initial impression for only that module.
	// Otherwise, log impressions for all modules.
	if ( !mw.config.get( 'homepagemobile' ) || !routeMatchesModule ) {
		$modules.each( logImpression );
	}

	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		$content.find( '.growthexperiments-homepage-module' )
			.on( 'click', '[data-link-id], [data-link-group-id] a', handleClick );
	} );
}() );
