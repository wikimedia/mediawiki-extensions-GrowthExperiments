( function () {
	if ( mw.eventLog === undefined ) {
		// EventLogging is not available.
		return;
	}

	const Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		useInstrument = require( '../ext.growthExperiments.Homepage.Logger/useInstrument.js' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		// eslint-disable-next-line no-jquery/no-global-selector
		$modules = $( '.growthexperiments-homepage-container .growthexperiments-homepage-module' ),
		streamName = 'mediawiki.product_metrics.homepage_module_interaction',
		schemaId = '/analytics/product_metrics/web/base/1.3.0',
		analytics = useInstrument( streamName, schemaId, {} ),
		handleClick = function ( e ) {
			const $link = $( this ),
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
			// Special casing for the SDS2.1.3 objective, T365889
			if ( moduleName === 'community-updates' ) {
				const actionData = mw.config.get( 'wgGEHomepageModuleActionData-community-updates' );
				analytics.logEvent( 'click', linkId, moduleName, actionData ? actionData.context : null );
			}

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			const $module = $( this ),
				moduleName = $module.data( 'module-name' ),
				mode = $module.data( 'mode' );
			logger.log( moduleName, mode, 'impression' );
			// Special casing for the SDS2.1.3 objective, T365889
			if ( moduleName === 'community-updates' ) {
				// TODO Expand to all modules once Community updates experiment concludes, T365889
				const actionData = mw.config.get( 'wgGEHomepageModuleActionData-community-updates' );
				analytics.logEvent( 'impression', null, moduleName, actionData ? actionData.context : null );
			}
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
