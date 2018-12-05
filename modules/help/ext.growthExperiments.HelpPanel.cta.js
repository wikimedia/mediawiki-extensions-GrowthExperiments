( function () {
	$( function () {
		var helpCtaButton = OO.ui.ButtonWidget.static.infuse( 'mw-ge-help-panel-cta' ),
			windowManager = new OO.ui.WindowManager( { modal: OO.ui.isMobile() } ),
			$overlay = $( '<div>' ).addClass( 'mw-ge-help-panel-widget-overlay' ),
			/**
			 * @type {OO.ui.Window}
			 */
			helpPanelProcessDialog = new mw.libs.ge.HelpPanelProcessDialog( {
				size: OO.ui.isMobile() ? 'full' : 'small'
			} ),
			lifecycle;

		$overlay.append( windowManager.$element );
		if ( !OO.ui.isMobile() ) {
			$overlay.addClass( 'mw-ge-help-panel-popup' )
				.append( windowManager.$element );
		}
		$( 'body' ).append( $overlay );
		windowManager.addWindows( [ helpPanelProcessDialog ] );

		helpCtaButton.on( 'click', function () {
			lifecycle = windowManager.openWindow( helpPanelProcessDialog );
			// Reset to home panel if user closed the widget.
			helpPanelProcessDialog.executeAction( 'home' );
			helpCtaButton.toggle( false );
			lifecycle.closing.done( function () {
				helpCtaButton.toggle( true );
			} );
		} );

	} );

}() );
