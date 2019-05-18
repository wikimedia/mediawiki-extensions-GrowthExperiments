( function ( gt ) {
	var mentorTour = new gt.TourBuilder( {
		name: 'mentor',
		shouldLog: true
	} );
	mentorTour.firstStep( {
		name: 'incomingmessage',
		titlemsg: 'growthexperiments-tour-mentor-response-tip-personal-title',
		descriptionmsg: 'growthexperiments-tour-mentor-response-tip-personal-text',
		attachTo: '#pt-notifications-alert',
		position: 'bottom',
		overlay: false,
		autoFocus: true,
		buttons: [ {
			action: 'end',
			namemsg: 'growthexperiments-tour-response-button-okay'
		} ]
	} );
}( mw.guidedTour ) );
