( function ( gt ) {
	var welcomeTour, step,
		homepageVariant = mw.user.options.get( 'growthexperiments-homepage-variant' );

	welcomeTour = new gt.TourBuilder( {
		name: 'homepage_welcome',
		isSinglePage: true,
		shouldLog: true
	} );
	if ( homepageVariant === 'A' ) {
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
	} else if ( homepageVariant === 'C' ) {
		step = welcomeTour.firstStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			description: mw.message( 'growthexperiments-tour-welcome-description-c' ).parse(),
			attachTo: '#pt-userpage',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				// There is way to influence the button icon without terrible hacks,
				// so use the 'next' button which has the right icon, and define a fake next step.
				action: 'next'
			} ]
		} );
		welcomeTour.step( {
			name: 'fake',
			description: 'also fake',
			onShow: function () {
				mw.guidedTour.endTour();
				mw.track( 'growthexperiments.startediting' );
				// cancel displaying the guider
				return true;
			}
		} );
		step.next( 'fake' );
	} else if ( homepageVariant === 'D' ) {
		welcomeTour.firstStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			description: mw.message( 'growthexperiments-tour-welcome-description-d' ).parse(),
			attachTo: '#pt-userpage',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				action: 'end',
				namemsg: 'growthexperiments-tour-response-button-okay'
			} ]
		} );
	}
	mw.guidedTour.launchTour( 'homepage_welcome' );
	new mw.Api().saveOption(
		'growthexperiments-tour-homepage-welcome',
		'1'
	);
}( mw.guidedTour ) );
