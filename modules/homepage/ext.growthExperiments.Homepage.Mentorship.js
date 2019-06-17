( function () {
	var attachButton = require( 'ext.growthExperiments.Homepage.QuestionPoster' ),
		userName = mw.user.getName(),
		mentorName = mw.config.get( 'GEHomepageMentorshipMentorName' ),
		mentorTalkLinkText = mw.message(
			'growthexperiments-homepage-mentorship-questionreview-header-mentor-talk-link-text',
			mentorName, userName
		).text(),
		mentorTalkLink = $( '<a>' )
			.attr( {
				href: mw.Title.newFromText( mentorName, 3 ).getUrl(),
				target: '_blank',
				'data-link-id': 'mentor-talk'
			} )
			.text( mentorTalkLinkText ),
		dialogTitle = mw.message( 'growthexperiments-homepage-mentorship-dialog-title',
			mentorName, userName ).text(),
		reviewHeader = mw.message( 'growthexperiments-homepage-mentorship-questionreview-header',
			mentorName, userName, mentorTalkLink ).parse(),
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
			mentorTalkLink
		).parse(),
		config = {
			buttonSelector: '#mw-ge-homepage-mentorship-cta',
			editorInterface: 'homepage_mentorship',
			dialog: {
				name: 'mentorship',
				panelTitleMessages: {
					questionreview: dialogTitle,
					questioncomplete: dialogTitle
				},
				questionReviewHeader: reviewHeader,
				questionCompleteConfirmationText: confirmationText,
				viewQuestionText: viewQuestionText,
				submitFailureMessage: submitFailureMessage
			}
		};
	// See comment in homepage/ext.growthExperiments.Homepage.Help.js
	attachButton( config );
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded.mentorship' ).add( function () {
		attachButton( config );
	} );
}() );
