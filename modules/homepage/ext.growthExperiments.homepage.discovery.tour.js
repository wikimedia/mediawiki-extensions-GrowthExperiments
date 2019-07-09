( function ( gt ) {
	var discoveryTour = new gt.TourBuilder( {
		name: 'homepage_discovery',
		isSinglePage: true,
		shouldLog: true
	} );
	discoveryTour.firstStep( {
		name: 'discovery',
		titlemsg: 'growthexperiments-tour-discovery-title',
		description: mw.message( 'growthexperiments-tour-discovery-description' )
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
	mw.guidedTour.launchTour( 'homepage_discovery' );
	new mw.Api().saveOption(
		'growthexperiments-tour-homepage-discovery',
		'1'
	);
}( mw.guidedTour ) );
