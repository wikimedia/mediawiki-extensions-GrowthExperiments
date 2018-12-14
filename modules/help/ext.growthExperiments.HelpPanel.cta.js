( function () {
	// This shouldn't happen, but just to be sure
	if ( !mw.config.get( 'wgGEHelpPanelEnabled' ) ) {
		return;
	}

	$( function () {
		var $buttonToInfuse = $( '#mw-ge-help-panel-cta' ),
			windowManager = new OO.ui.WindowManager( { modal: OO.ui.isMobile() } ),
			$overlay = $( '<div>' ).addClass( 'mw-ge-help-panel-widget-overlay' ),
			loggingEnabled = mw.config.get( 'wgGEHelpPanelLoggingEnabled' ),
			logger = new mw.libs.ge.HelpPanelLogger( loggingEnabled ),
			/**
			 * @type {OO.ui.Window}
			 */
			helpPanelProcessDialog = new mw.libs.ge.HelpPanelProcessDialog( {
				size: OO.ui.isMobile() ? 'full' : 'small',
				$overlay: $overlay,
				logger: logger
			} ),
			helpCtaButton,
			lifecycle;

		if ( $buttonToInfuse.length ) {
			helpCtaButton = OO.ui.ButtonWidget.static.infuse( $buttonToInfuse );
		} else {
			helpCtaButton = new OO.ui.ButtonWidget( {
				classes: [ 'mw-ge-help-panel-cta' ],
				id: 'mw-ge-help-panel-cta',
				href: mw.util.getUrl( mw.config.get( 'wgGEHelpPanelHelpDeskTitle' ) ),
				label: mw.msg( 'growthexperiments-help-panel-cta-button-text' ),
				icon: 'helpNotice',
				flags: [ 'primary', 'progressive' ]
			} );
			$overlay.append( helpCtaButton.$element );
		}

		mw.hook( 've.activationComplete' ).add( function () {
			// If helpCtaButton was in the initial HTML, it's inside the mw-content-text div,
			// which is now hidden. Reattach it to the overlay instead.
			$overlay.append( helpCtaButton.$element );
		} );

		mw.hook( 've.deactivationComplete' ).add( function () {
			// Hide helpCtaButton when the editor is closed
			helpCtaButton.$element.detach();
		} );

		$overlay.append( windowManager.$element );
		if ( !OO.ui.isMobile() ) {
			$overlay.addClass( 'mw-ge-help-panel-popup' );
		}
		$( 'body' ).append( $overlay );
		windowManager.addWindows( [ helpPanelProcessDialog ] );

		logger.log( 'impression' );
		helpCtaButton.on( 'click', function () {
			lifecycle = windowManager.openWindow( helpPanelProcessDialog );
			// Reset to home panel if user closed the widget.
			helpPanelProcessDialog.executeAction( 'reset' );
			helpCtaButton.toggle( false );
			logger.log( 'open' );
			lifecycle.closing.done( function () {
				helpCtaButton.toggle( true );
				logger.log( 'close' );
			} );
		} );

	} );

}() );
