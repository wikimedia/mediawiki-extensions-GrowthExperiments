( function ( gt ) {
	var mentorTour,
		tourUtils = require( '../utils/tourUtils.js' );

	mentorTour = new gt.TourBuilder( {
		name: 'homepage_mentor',
		isSinglePage: true,
		shouldLog: true
	} );
	mentorTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
		name: 'incomingmessage',
		titlemsg: 'growthexperiments-tour-mentor-response-tip-personal-title',
		description: mw.message( 'growthexperiments-tour-mentor-response-tip-personal-text' )
			.params( [ mw.user, mw.config.get( 'GEHomepageMentorshipMentorGender' ) ] )
			.parse(),
		attachTo: '#pt-notifications-alert',
		position: 'bottom',
		overlay: false,
		autoFocus: true,
		buttons: [ {
			action: 'end',
			namemsg: 'growthexperiments-tour-response-button-okay'
		} ]
	} ) );
}( mw.guidedTour ) );
