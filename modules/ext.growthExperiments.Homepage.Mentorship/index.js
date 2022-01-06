( function () {
	var attachButton = require( './QuestionPoster.js' ),
		config = {
			buttonSelector: '#mw-ge-homepage-mentorship-cta',
			context: 'homepage_mentorship',
			dialog: {
				name: 'mentorship'
			}
		};

	// attachButton will look to see if the CTA button we want to bind to is in the DOM. For the
	// desktop homepage and the server-side rendered version of individual modules (e.g
	// Special:Homepage/mentorship) that will be the case. But this module is loaded on
	// Special:Homepage for mobile overlays, where the DOM is only populated when the user opens
	// the overlay for the first time, so we can only attach the button once that has happened.
	// eslint-disable-next-line no-jquery/no-global-selector
	attachButton( config, $( '.growthexperiments-homepage-container' ) );
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'mentorship' ) {
			attachButton( config, $content );
		}
	} );
}() );
