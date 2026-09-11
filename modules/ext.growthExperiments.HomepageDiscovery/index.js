( function () {
	'use strict';
	if ( mw.loader.getState( 'mobile.init' ) ) {
		mw.loader.using( 'mobile.init' ).then( () => {
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.mw-ge-homepage-discovery-banner-close' ).on( 'click', function () {
				$( this ).closest( '.mw-ge-homepage-discovery-banner-mobile' ).remove();
			} );

			const suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();
			// eslint-disable-next-line no-jquery/no-global-selector
			const $returnToHomepageBanner = $( '.ext-growthexperiments-homepage-return-to-banner' );
			if ( !suggestedEditSession.active && $returnToHomepageBanner.length > 0 ) {
				$returnToHomepageBanner.show();
				const api = new mw.Api();
				api.saveOption( 'growthexperiments-tour-homepage-welcome', '1' );
			}
		} );
	}
}() );
