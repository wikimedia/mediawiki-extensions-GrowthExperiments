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
				overlayManager = OverlayManager.getSingleton(),
				lazyLoadModules = [];

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
					// We don't want to log a close event for the overlay when the user has tapped
					// on the question dialog, which hides the MobileOverlay. To enforce that we
					// need to check:
					// - moduleName is not set to e.g. help/question or mentorship/question
					// - router path is not one of /homepage/help/question or
					//   /homepage/mentorship/question
					// Example:
					// 1. when you click the "Ask the help desk CTA", moduleName is help and
					//    router.getPath() is /homepage/help/question.
					// 2. When you close the process dialog, moduleName is  help/question and
					//    router.getPath() is /homepage/help.
					// 3. When you close the overlay, moduleName is help and router.getPath()
					//    is /homepage/help
					// We can't only check the router path since /homepage/help is the router
					// path for both  the event we want to log (closing the overlay) and the
					// event we don't want to log (closing the process dialog).
					// If we changed the regex in overlayManager.add( /^\/homepage\/(.*)$/ )
					// so that it only activated on homepage/{moduleName} and not
					// homepage/{moduleName}/question, the user would briefly see the overlay
					// vanish and then the mobile summary view (which was behind the overlay)
					// before the process dialog opened up, which is a poor user experience.
					// So the convoluted logic and lengthy comment here is a tradeoff for
					// better user experience.
					if ( moduleName.indexOf( '/' ) === -1 && !router.getPath().match( /^\/homepage\/.*\/question$/ ) ) {
						logger.log( moduleName, 'mobile-overlay', 'close' );
					}
				} );
			} );

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
