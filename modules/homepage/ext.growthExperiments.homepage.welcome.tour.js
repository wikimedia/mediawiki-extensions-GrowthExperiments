( function ( gt ) {
	var welcomeTour = new gt.TourBuilder( {
		name: 'homepage_welcome',
		isSinglePage: true,
		shouldLog: true
	} );
	welcomeTour.firstStep( {
		name: 'welcome',
		title: mw.message( 'growthexperiments-tour-welcome-title' )
			.params( [ mw.user ] )
			.parse(),
		description: mw.message( 'growthexperiments-tour-welcome-description' )
			.params( [ mw.user ] )
			.parse(),
		attachTo: '#pt-userpage',
		position: 'bottom',
		overlay: false,
		autoFocus: true,
		buttons: [ {
			action: 'end',
			namemsg: 'growthexperiments-tour-response-button-okay'
		} ]
	} );
	mw.guidedTour.launchTour( 'homepage_welcome' );
	new mw.Api().saveOption(
		'growthexperiments-tour-homepage-welcome',
		'1'
	);
}( mw.guidedTour ) );
