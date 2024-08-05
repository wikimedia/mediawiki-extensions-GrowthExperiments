( function () {
	'use strict';
	module.exports = {
		/**
		 * Show custom email confirmation warning when the user focuses on the email field,
		 * hide the warning if the user doesn't provide an email address
		 */
		maybeShowWarning: function () {
			mw.hook( 'htmlform.enhance' ).add( ( $root ) => {
				var $emailInput = $root.find( '#wpEmail' );
				$emailInput
					.on( 'focus', () => {
						var $warningBox = $emailInput.next( '.cdx-message--warning' );
						if ( $warningBox.length === 0 ) {
							$warningBox = $( '<div>' )
								.addClass( 'cdx-message cdx-message--block cdx-message--warning' )
								.attr( 'aria-live', 'polite' )
								.append( $( '<span>' ).addClass( 'cdx-message__icon' ) )
								.append( $( '<div>' )
									.addClass( 'cdx-message__content' )
									.text( mw.msg( 'growthexperiments-confirmemail-emailwarning' ) )
								)
								.hide();
							$emailInput.after( $warningBox );
						}
						// eslint-disable-next-line no-jquery/no-slide
						$warningBox.slideDown();
					} )
					.on( 'blur', () => {
						// Hide the warning again if the user leaves the email input without
						// typing anything
						var $warningBox = $emailInput.next( '.cdx-message--warning' );
						if ( $warningBox.length && $emailInput.val().trim() === '' ) {
							// eslint-disable-next-line no-jquery/no-slide
							$warningBox.slideUp();
						}
					} );
			} );
		}
	};
}() );
