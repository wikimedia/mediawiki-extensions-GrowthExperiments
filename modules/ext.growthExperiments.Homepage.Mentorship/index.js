( function () {
	const questionPosterAttachButton = require( './QuestionPoster.js' ),
		optInAttachButton = require( './OptIn.js' ),
		initEllipsisMenu = require( './EllipsisMenu.js' ),
		questionPosterConfig = {
			buttonSelector: '#mw-ge-homepage-mentorship-cta',
			context: 'homepage_mentorship',
			dialog: {
				name: 'mentorship',
			},
		},
		optInConfig = {
			buttonSelector: '#mw-ge-homepage-mentorship-optin',
		},
		// eslint-disable-next-line no-jquery/no-global-selector
		$homepageContainer = $( '.growthexperiments-homepage-container' );

	// questionPosterAttachButton and optInAttachButton will look to see if the CTA button we want to bind to is
	// in the DOM. For the desktop homepage and the server-side rendered version of individual modules (e.g
	// Special:Homepage/mentorship) that will be the case. But this module is loaded on
	// Special:Homepage for mobile overlays, where the DOM is only populated when the user opens
	// the overlay for the first time, so we can only attach the button once that has happened.
	questionPosterAttachButton( questionPosterConfig, $homepageContainer );
	optInAttachButton( optInConfig, $homepageContainer );
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( ( moduleName, $content ) => {
		if ( moduleName === 'mentorship' ) {
			questionPosterAttachButton( questionPosterConfig, $content );
		}

		if ( moduleName === 'mentorship-optin' ) {
			optInAttachButton( optInConfig, $content );
		}
	} );

	require( './RecentQuestions.js' );
	initEllipsisMenu( $homepageContainer );
}() );
