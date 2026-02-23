( function () {
	if ( mw.eventLog === undefined ) {
		// EventLogging is not available.
		return;
	}

	const useInstrument = require(
			'../ext.growthExperiments.Homepage.Logger/useInstrument.js' ),
		// eslint-disable-next-line no-jquery/no-global-selector
		$modules = $( '.growthexperiments-homepage-container .growthexperiments-homepage-module' ),
		streamName = 'mediawiki.product_metrics.homepage_module_interaction',
		schemaId = '/analytics/product_metrics/web/base/2.0.0',
		analytics = useInstrument( streamName, schemaId ),
		handleClick = function ( e ) {
			const $link = $( this ),
				$module = $link.closest( '.growthexperiments-homepage-module' ),
				linkId = $link.data( 'link-id' ) ||
					$link.closest( '[data-link-group-id]' ).data( 'link-group-id' ),
				moduleName = $module.data( 'module-name' ),
				actionContext = analytics.getActionContextForSchema( moduleName );

			analytics.logEvent( 'click', linkId, moduleName, actionContext );

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			const $module = $( this ),
				moduleName = $module.data( 'module-name' ),
				actionContext = analytics.getActionContextForSchema( moduleName );

			analytics.logEvent( 'impression', null, moduleName, actionContext );
		},
		url = new URL( window.location.href ),
		// Matches routes like /homepage/moduleName or /homepage/moduleName/action
		// FIXME or describe why it is okay

		// Using URL.hash but removing the leading # character
		routeMatches = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/.exec(
			url.hash.startsWith( '#' ) ? url.hash.slice( 1 ) : url.hash ),
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

	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( ( moduleName, $content ) => {
		$content.find( '.growthexperiments-homepage-module' )
			.on( 'click', '[data-link-id], [data-link-group-id] a', handleClick );
	} );
}() );
