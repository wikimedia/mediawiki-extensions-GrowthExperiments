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
	// for mobile overlays, and the user may open and close the help/mentorship module overlays
	// more than once. When that happens, we need to (re)attach the button, as the HTML is newly
	// added to the overlay each time it's opened.
	attachButton( config );
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName ) {
		if ( moduleName === 'help' ) {
			attachButton( config );
		}
	} );
}() );
