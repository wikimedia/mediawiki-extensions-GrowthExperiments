( function () {
	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		var $emailInput = $root.find( '#wpEmail' );
		$emailInput
			.on( 'focus', function () {
				var $warningBox = $emailInput.next( '.warning' );
				if ( $warningBox.length === 0 ) {
					$warningBox = $( '<span>' )
						.addClass( 'warning' )
						.text( mw.msg( 'growthexperiments-confirmemail-emailwarning' ) )
						.hide();
					$emailInput.after( $warningBox );
				}
				// eslint-disable-next-line no-jquery/no-slide
				$warningBox.slideDown();
			} )
			.on( 'blur', function () {
				// Hide the warning again if the user leaves the email input without typing anything
				var $warningBox = $emailInput.next( '.warning' );
				if ( $warningBox.length && $emailInput.val().trim() === '' ) {
					// eslint-disable-next-line no-jquery/no-slide
					$warningBox.slideUp();
				}
			} );
	} );
}() );
