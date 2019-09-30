( function () {
	var ctaButton,
		// eslint-disable-next-line no-jquery/no-global-selector
		$buttonElement = $( '#mw-ge-homepage-startediting-cta' );

	if ( $buttonElement.length === 0 ) {
		return;
	}
	ctaButton = OO.ui.ButtonWidget.static.infuse( $buttonElement );

	ctaButton.on( 'click', function () {
		// TODO open dialog
		ctaButton.setDisabled( true );
		new mw.Api().saveOption( 'growthexperiments-homepage-suggestededits-activated', 1 )
			.then( function () {
				window.location.reload();
			} );
	} );
}() );
