( function () {
	var Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		handleHover = function ( action ) {
			return function () {
				var $module = $( this ),
					moduleName = $module.data( 'module-name' ),
					mobileModes = [ 'mobile-overlay', 'mobile-details', 'mobile-summary' ],
					mode = $module.data( 'mode' );
				if ( mobileModes.indexOf( mode ) === -1 ) {
					logger.log( moduleName, mode, 'hover-' + action );
				}
			};
		},
		moduleSelector = '.growthexperiments-homepage-container .growthexperiments-homepage-module',
		$modules = $( moduleSelector ),
		handleClick = function ( e ) {
			var $link = $( this ),
				$module = $link.closest( moduleSelector ),
				linkId = $link.data( 'link-id' ),
				moduleName = $module.data( 'module-name' ),
				mode = $module.data( 'mode' );
			logger.log( moduleName, mode, 'link-click', { linkId: linkId } );

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
		.on( 'mouseenter', handleHover( 'in' ) )
		.on( 'mouseleave', handleHover( 'out' ) )
		.on( 'click', '[data-link-id]', handleClick );

	// If we're on mobile and the initial URI specifies a module to navigate to, let
	// ext.growthExperiments.Homepage.Mobile.js log the intiial impression for only that module.
	// Otherwise, log impressions for all modules.
	if ( !mw.config.get( 'homepagemobile' ) || !routeMatchesModule ) {
		$modules.each( logImpression );
	}

	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		$content.find( moduleSelector )
			.on( 'click', '[data-link-id]', handleClick );
	} );
}() );
