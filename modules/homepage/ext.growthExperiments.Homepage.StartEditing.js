( function () {
	var ctaButton, windowManager, dialog,
		StartEditingDialog = require( './ext.growthExperiments.Homepage.StartEditingDialog.js' ),
		// eslint-disable-next-line no-jquery/no-global-selector
		$buttonElement = $( '#mw-ge-homepage-startediting-cta' );

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
				window.location.reload();
			}
		} );
		// TODO maybe restructure the page without refreshing?
		// Would require AJAX for the new module's contents
	} );
}() );
