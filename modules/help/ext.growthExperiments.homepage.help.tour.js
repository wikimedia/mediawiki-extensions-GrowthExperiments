( function ( gt ) {
	var dashboardTour = new gt.TourBuilder( {
		name: 'homepage_help',
		isSinglePage: true,
		shouldLog: true
	} );
	dashboardTour.firstStep( {
		name: 'incomingmessage',
		titlemsg: 'growthexperiments-tour-helpdesk-response-tip-title',
		descriptionmsg: 'growthexperiments-tour-response-tip-text',
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
