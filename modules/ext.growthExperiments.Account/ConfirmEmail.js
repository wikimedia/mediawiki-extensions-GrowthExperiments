( function () {
	'use strict';
	module.exports = {
		/**
		 * Show custom email confirmation warning when the user focuses on the email field,
		 * hide the warning if the user doesn't provide an email address
		 */
		maybeShowWarning: function () {
			mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
				var $emailInput = $root.find( '#wpEmail' );
				$emailInput
					.on( 'focus', function () {
						var $warningBox = $emailInput.next( '.warningbox' );
						if ( $warningBox.length === 0 ) {
							$warningBox = $( '<div>' )
								.addClass( 'warningbox' )
								.text( mw.msg( 'growthexperiments-confirmemail-emailwarning' ) )
								.hide();
							$emailInput.after( $warningBox );
						}
						// eslint-disable-next-line no-jquery/no-slide
						$warningBox.slideDown();
					} )
					.on( 'blur', function () {
						// Hide the warning again if the user leaves the email input without
						// typing anything
						var $warningBox = $emailInput.next( '.warningbox' );
						if ( $warningBox.length && $emailInput.val().trim() === '' ) {
							// eslint-disable-next-line no-jquery/no-slide
							$warningBox.slideUp();
						}
					} );
			} );
		}
	};
}() );
