( function () {
	var StartEditingDialog = require( './ext.growthExperiments.Homepage.StartEditingDialog.js' );

	function setupCta( $container ) {
		var ctaButton, dialog, windowManager,
			$buttonElement = $container.find( '#mw-ge-homepage-startediting-cta' ),
			mode = $buttonElement.closest( '.growthexperiments-homepage-module' ).data( 'mode' );
		if ( $buttonElement.length === 0 ) {
			return;
		}

		ctaButton = OO.ui.ButtonWidget.static.infuse( $buttonElement );
		dialog = new StartEditingDialog();
		windowManager = new OO.ui.WindowManager( { modal: true } );

		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );

		ctaButton.on( 'click', function () {
			var lifecycle = windowManager.openWindow( dialog );
			lifecycle.closing.done( function ( data ) {
				if ( data && data.action === 'activate' ) {
					ctaButton.setDisabled( true );
					if ( mode === 'mobile-overlay' ) {
						window.history.pushState( null, null, '#/homepage/suggested-edits' );
						window.location.reload();
					} else if ( mode === 'mobile-details' ) {
						window.location.href = mw.util.getUrl( new mw.Title( 'Special:Homepage/suggested-edits' ).toString() );
					} else {
						window.location.reload();
					}
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
