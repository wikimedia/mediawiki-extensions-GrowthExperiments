( function ( gt ) {
	var dashboardTour = new gt.TourBuilder( {
		name: 'helppanel',
		isSinglePage: true,
		shouldLog: true
	} );
	dashboardTour.firstStep( {
		name: 'incomingmessage',
		titlemsg: 'growthexperiments-tour-helpdesk-response-tip-title',
		description: mw.message( 'growthexperiments-tour-response-tip-text' )
			.params( [ mw.user ] )
			.parse(),
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
