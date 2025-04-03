( function () {
	'use strict';
	if ( mw.loader.getState( 'mobile.init' ) ) {
		mw.loader.using( 'mobile.init' ).then( () => {
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.mw-ge-homepage-discovery-banner-close' ).on( 'click', function () {
				$( this ).closest( '.mw-ge-homepage-discovery-banner-mobile' ).remove();
			} );
		} );
	}
}() );
