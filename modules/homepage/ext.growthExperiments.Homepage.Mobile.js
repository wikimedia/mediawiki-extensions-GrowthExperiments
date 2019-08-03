( function ( $, mw ) {
	'use strict';
	if ( mw.loader.getState( 'mobile.init' ) ) {
		mw.loader.using( 'mobile.init' ).done( function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			var $homepageSummaryModules = $( '.growthexperiments-homepage-container > a > .growthexperiments-homepage-module' ),
				// eslint-disable-next-line no-jquery/no-global-selector
				$overlayModules = $( '.growthexperiments-homepage-overlay-container' ),
				MobileOverlay = require( './ext.growthExperiments.Homepage.MobileOverlay.js' ),
				OverlayManager = mw.mobileFrontend.require( 'mobile.startup' ).OverlayManager,
				Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
				logger = new Logger(
					mw.config.get( 'wgGEHomepageLoggingEnabled' ),
					mw.config.get( 'wgGEHomepagePageviewToken' )
				),
				router = require( 'mediawiki.router' ),
				overlayManager = OverlayManager.getSingleton(),
				lazyLoadModules = [],
				overlays = {},
				currentModule = null,
				// Matches routes like /homepage/moduleName or /homepage/moduleName/action
				routeRegex = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/;

			/**
			 * Extract module detail HTML, heading and RL modules config var.
			 *
			 * @param {string} moduleName
			 * @return {Object}
			 */
			function getModuleData( moduleName ) {
				var data = mw.config.get( 'homepagemodules' )[ moduleName ];
				data.html = $overlayModules.find( '[data-module-name="' + moduleName + '"]' ).clone();
				return data;
			}

			function getSubmodules( moduleName ) {
				// HACK: extract submodule info from the module HTML
				return $( getModuleData( moduleName ).html )
					.find( '.growthexperiments-homepage-module' )
					.toArray()
					.map( function ( moduleElement ) {
						return $( moduleElement ).data( 'module-name' );
					} )
					// With the current HTML structure, this shouldn't return the module itself,
					// but filter it out just to be sure
					.filter( function ( submodule ) {
						return submodule !== moduleName;
					} );
			}

			function handleRouteChange( path ) {
				var matches = path.match( routeRegex ),
					newModule = matches ? matches[ 1 ] : null;

				// Log mobile-overlay open/close when navigating to / away from a module
				// We can't do this in a show/hide event handler on the overlay itself, because it
				// gets hidden then shown again when opening and closing the question dialog
				// (due to navigation from #/homepage/moduleName to #homepage/moduleName/question)
				if ( newModule !== currentModule ) {
					if ( currentModule !== null ) {
						// Navigating away from a module: log closing the overlay
						logger.log( currentModule, 'mobile-overlay', 'close' );
					}
					if ( newModule !== null ) {
						// Navigating to a module: log impression
						logger.log( newModule, 'mobile-overlay', 'impression' );

						// Find submodules in the new module, and log impressions for them
						getSubmodules( newModule ).forEach( function ( submodule ) {
							logger.log( submodule, 'mobile-overlay', 'impression' );
						} );
					}
				}

				currentModule = newModule;
			}

			overlayManager.add( routeRegex, function ( moduleName ) {
				if ( overlays[ moduleName ] === undefined ) {
					overlays[ moduleName ] = new MobileOverlay(
						$.extend( { moduleName: moduleName }, getModuleData( moduleName ) )
					);
				}
				return overlays[ moduleName ];
			} );

			router.on( 'route', function ( ev ) {
				handleRouteChange( ev.path );
			} );

			// Initialize state for handleRouteChange, and log initial impression if needed
			handleRouteChange( router.getPath() );

			$homepageSummaryModules.on( 'click', function ( e ) {
				e.preventDefault();
				router.navigate( '#/homepage/' + $( this ).data( 'module-name' ) );
			} );

			// Start loading the ResourceLoader modules so that tapping on one will load
			// instantly. We don't load these with page delivery so as to speed up the
			// initial page load.
			$homepageSummaryModules.each( function () {
				var moduleName = $( this ).data( 'module-name' ),
					rlModules = getModuleData( moduleName ).rlModules;
				if ( rlModules ) {
					lazyLoadModules = lazyLoadModules.concat( rlModules );
				}
			} );
			setTimeout( function () {
				mw.loader.load( lazyLoadModules );
			}, 250 );

		} );
	}
}( jQuery, mediaWiki ) );
