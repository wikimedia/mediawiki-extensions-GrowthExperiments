/* eslint-disable no-jquery/no-global-selector, no-jquery/no-class-state */
( function () {
	'use strict';

	const $body = $( 'body' );
	if ( $body.hasClass( 'skin-vector' ) && !$body.hasClass( 'skin-vector-legacy' ) ) {
		// exclude modern vector, because the dot is not visible there (and hidden in a menu)
		// TODO: Make the dot appear in modern Vector (T290644).
		return;
	}

	$( '#pt-mentordashboard a' ).append(
		$( '<div>' ).addClass( 'mw-pulsating-dot' )
	);

}() );
