( function ( gt ) {
	const tourUtils = require( './tourUtils.js' ),
		mentorGender = mw.config.get( 'GEHomepageMentorshipMentorGender' );

	const mentorTour = new gt.TourBuilder( {
		name: 'homepage_mentor',
		isSinglePage: true,
		shouldLog: true,
	} );
	mentorTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
		name: 'incomingmessage',
		title: mw.message( 'growthexperiments-tour-mentor-response-tip-personal-title', mentorGender ).text(),
		description: mw.message( 'growthexperiments-tour-mentor-response-tip-personal-text' )
			.params( [ mw.user, mentorGender ] )
			.parse(),
		attachTo: '#pt-notifications-alert',
		position: 'bottom',
		overlay: false,
		autoFocus: true,
		buttons: [ {
			action: 'end',
			namemsg: 'growthexperiments-tour-response-button-okay',
		} ],
	} ) );
}( mw.guidedTour ) );
