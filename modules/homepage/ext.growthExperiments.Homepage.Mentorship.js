( function () {
	var attachButton = require( 'ext.growthExperiments.Homepage.QuestionPoster' ),
		userName = mw.user.getName(),
		mentorName = mw.config.get( 'GEHomepageMentorshipMentorName' ),
		mentorTalkLinkText = mw.message(
			'growthexperiments-homepage-mentorship-questionreview-header-mentor-talk-link-text',
			mentorName, userName
		).text(),
		$mentorTalkLink = $( '<a>' )
			.attr( {
				href: mw.Title.newFromText( mentorName, 3 ).getUrl(),
				target: '_blank',
				'data-link-id': 'mentor-talk'
			} )
			.text( mentorTalkLinkText ),
		dialogTitle = mw.message( 'growthexperiments-homepage-mentorship-dialog-title',
			mentorName, userName ).text(),
		reviewHeader = mw.message( 'growthexperiments-homepage-mentorship-questionreview-header',
			mentorName, userName, $mentorTalkLink ).parse(),
		confirmationText = mw.message(
			'growthexperiments-homepage-mentorship-confirmation-text',
			mentorName, userName
		).text(),
		viewQuestionText = mw.message(
			'growthexperiments-homepage-mentorship-view-question-text',
			mentorName, userName
		).text(),
		submitFailureMessage = mw.message(
			'growthexperiments-help-panel-question-post-error',
			$mentorTalkLink
		).parse(),
		config = {
			buttonSelector: '#mw-ge-homepage-mentorship-cta',
			context: 'homepage_mentorship',
			dialog: {
				name: 'mentorship',
				panelTitleMessages: {
					'ask-help': dialogTitle,
					questioncomplete: dialogTitle
				},
				askhelpHeader: reviewHeader,
				questionCompleteConfirmationText: confirmationText,
				viewQuestionText: viewQuestionText,
				submitFailureMessage: submitFailureMessage
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
