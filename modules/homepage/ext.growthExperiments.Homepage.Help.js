( function () {
	var attachButton = require( 'ext.growthExperiments.Homepage.QuestionPoster' ),
		dialogTitle = mw.message( 'growthexperiments-homepage-help-dialog-title' ).text(),
		config = {
			buttonSelector: '#mw-ge-homepage-help-cta',
			editorInterface: 'homepage_help',
			dialog: {
				name: 'help',
				panelTitleMessages: {
					questionreview: dialogTitle,
					questioncomplete: dialogTitle
				}
			}
		};

	// attachButton will look to see if the CTA button we want to bind to is in the DOM. For the
	// desktop homepage and the server-side rendered version of individual modules (e.g
	// Special:Homepage/help) that will be the case. But this module is loaded on Special:Homepage
	// for mobile overlays, where the DOM is only populated when the user opens the overlay for the
	// first time, so we can only attach the button once that has happened.

	// eslint-disable-next-line no-jquery/no-global-selector
	attachButton( config, $( '.growthexperiments-homepage-container' ) );
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'help' ) {
			// FIXME: This is why loading #homepage/help/question from the URL doesn't work:
			// attachButton() registers the route but that happens too late
			attachButton( config, $content );
		}
	} );
}() );
