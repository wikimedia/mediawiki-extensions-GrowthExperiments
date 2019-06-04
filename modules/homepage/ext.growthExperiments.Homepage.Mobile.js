( function ( $, mw ) {
	'use strict';
	if ( mw.loader.getState( 'mobile.init' ) ) {
		mw.loader.using( 'mobile.init' ).done( function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			var $homepageSummaryModules = $( '.growthexperiments-homepage-container > a > .growthexperiments-homepage-module' ),
				MobileOverlay = require( './ext.growthExperiments.Homepage.MobileOverlay.js' ),
				OverlayManager = mw.mobileFrontend.require( 'mobile.startup' ).OverlayManager,
				Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
				logger = new Logger(
					mw.config.get( 'wgGEHomepageLoggingEnabled' ),
					mw.config.get( 'wgGEHomepagePageviewToken' )
				),
				router = require( 'mediawiki.router' ),
				overlayManager = OverlayManager.getSingleton();

			/**
			 * Extract module detail HTML, heading and RL modules config var.
			 *
			 * @param {string} moduleName
			 * @return {Object}
			 */
			function getModuleData( moduleName ) {
				return mw.config.get( 'homepagemodules' )[ moduleName ];
			}

			overlayManager.add( /^\/homepage\/(.*)$/, function ( moduleName ) {
				return new MobileOverlay(
					$.extend( { moduleName: moduleName }, getModuleData( moduleName ) )
				).on( 'hide', function () {
					// TODO: Implement logging in a single place, and double-check params.
					logger.log( moduleName, 'back', { mode: 'overlay' } );
				} );
			} );

			$homepageSummaryModules.each( function ( index, module ) {
				var moduleName = $( module ).data( 'module-name' );
				// Start loading the ResourceLoader modules so that tapping on one will load
				// instantly. We don't load these with page delivery so as to speed up the
				// initial page load.
				setTimeout( function () {
					mw.loader.load( getModuleData( moduleName ).rlModules || [] );
				}, 250 );
				$( module ).on( 'click', function ( e ) {
					e.preventDefault();
					// TODO: Implement logging in a single place, and double-check params.
					logger.log( moduleName, 'click', { mode: 'overlay' } );
					router.navigate( '#/homepage/' + moduleName );
				} );
			} );

		} );
	}
}( jQuery, mediaWiki ) );
