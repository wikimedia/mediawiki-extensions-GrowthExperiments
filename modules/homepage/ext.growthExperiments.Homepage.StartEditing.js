( function () {
	var StartEditingDialog = require( './ext.growthExperiments.Homepage.StartEditingDialog.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		);

	function setupCta( $container ) {
		var ctaButton, dialog, windowManager,
			$buttonElement = $container.find( '#mw-ge-homepage-startediting-cta' ),
			mode = $buttonElement.closest( '.growthexperiments-homepage-module' ).data( 'mode' );
		if ( $buttonElement.length === 0 ) {
			return;
		}

		ctaButton = OO.ui.ButtonWidget.static.infuse( $buttonElement );
		dialog = new StartEditingDialog( { mode: mode }, logger );
		windowManager = new OO.ui.WindowManager( { modal: true } );

		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );

		ctaButton.on( 'click', function () {
			var lifecycle;
			if ( mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' ) ) {
				// already set up, just open suggested edits
				if ( mode === 'mobile-overlay' ) {
					// we don't want users to return to the start overlay when they close
					// suggested edits
					window.history.replaceState( null, null, '#/homepage/suggested-edits' );
					window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
				} else if ( mode === 'mobile-details' ) {
					window.location.href = mw.util.getUrl( new mw.Title( 'Special:Homepage/suggested-edits' ).toString() );
				}
				return;
			}

			lifecycle = windowManager.openWindow( dialog );
			logger.log( 'start-startediting', mode, 'se-cta-click' );
			lifecycle.closing.done( function ( data ) {
				if ( data && data.action === 'activate' ) {
					logger.log( 'start-startediting', mode, 'se-activate' );
					ctaButton.setDisabled( true );
					if ( mode === 'mobile-overlay' ) {
						window.history.pushState( null, null, '#/homepage/suggested-edits' );
						window.location.reload();
					} else if ( mode === 'mobile-details' ) {
						window.location.href = mw.util.getUrl( new mw.Title( 'Special:Homepage/suggested-edits' ).toString() );
					} else {
						window.location.reload();
					}
				} else {
					logger.log( 'start-startediting', mode, 'se-cancel-activation' );
				}
			} );
			// TODO maybe restructure the page without refreshing?
			// Would require AJAX for the new module's contents
		} );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js
	// eslint-disable-next-line no-jquery/no-global-selector
	setupCta( $( '.growthexperiments-homepage-container' ) );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'start' ) {
			setupCta( $content );
		}
	} );

}() );
