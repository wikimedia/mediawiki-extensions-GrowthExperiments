( function () {
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '.welcomesurvey-reminder-dismiss' ).on( 'click', function ( e ) {
		var that = this,
			apiUrl = $( this ).data( 'ajax' ),
			$form = $( this ).closest( 'form' );
		$.post( apiUrl, $form.serialize() ).then( function () {
			$( that ).closest( '.growthexperiments-homepage-module-welcomesurveyreminder' )
				.addClass( 'fadeout' );
		}, function ( jqXHR ) {
			var statusCode = jqXHR.status,
				statusText = jqXHR.statusText,
				data = jqXHR.responseJSON,
				errorDetail = data ? data.message : ( statusCode + ' ' + statusText ),
				error = 'Error dismissing welcome survey reminder: ' + errorDetail;
			mw.log.error( error );
			if ( statusCode >= 500 ) {
				mw.errorLogger.logError( new Error( error ), 'error.growthexperiments' );
			}
		} );
		// Prevent non-AJAX navigation to /skip but don't prevent event propagation, it's needed
		// for link click logging.
		e.preventDefault();
	} );
}() );
