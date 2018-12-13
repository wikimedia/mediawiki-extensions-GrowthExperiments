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

		/**
		 * Invoked from mobileFrontend.editorOpened and ve.activationComplete hooks.
		 *
		 * The CTA needs to be attached to the MobileFrontend or VisualEditor overlay.
		 */
		function attachHelpButton() {
			$overlay.append( helpCtaButton.$element );
		}

		/**
		 * Invoked from mobileFrontend.editorClosed and ve.deactivationComplete hooks.
		 *
		 * Hide the CTA when the MobileFrontend or VisualEditor overlay is closed.
		 */
		function detachHelpButton() {
			helpCtaButton.$element.detach();
		}

		if ( $buttonToInfuse.length ) {
			helpCtaButton = OO.ui.ButtonWidget.static.infuse( $buttonToInfuse );
		} else {
			helpCtaButton = new OO.ui.ButtonWidget( {
				classes: [ 'mw-ge-help-panel-cta' ],
				id: 'mw-ge-help-panel-cta',
				href: mw.util.getUrl( mw.config.get( 'wgGEHelpPanelHelpDeskTitle' ) ),
				label: OO.ui.isMobile() ? '' : mw.msg( 'growthexperiments-help-panel-cta-button-text' ),
				icon: 'askQuestion',
				flags: [ 'primary', 'progressive' ]
			} );
			$overlay.append( helpCtaButton.$element );
		}

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

		// Attach or detach the help panel CTA in response to hooks from MobileFrontend.
		if ( OO.ui.isMobile() ) {
			mw.hook( 'mobileFrontend.editorOpened' ).add( attachHelpButton );
			mw.hook( 'mobileFrontend.editorClosed' ).add( detachHelpButton );
		} else {
			// VisualEditor activation hooks are ignored in mobile context because MobileFrontend
			// hooks are sufficient for attaching/detaching the help CTA.
			mw.hook( 've.activationComplete' ).add( attachHelpButton );
			mw.hook( 've.deactivationComplete' ).add( detachHelpButton );
		}
	} );

}() );
