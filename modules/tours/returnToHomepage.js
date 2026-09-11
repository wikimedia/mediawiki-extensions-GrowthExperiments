( function ( gt ) {
	const suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();
	if ( suggestedEditSession.active ) {
		return;
	}
	const tourUtils = require( './tourUtils.js' );

	function markTourAsSeen() {
		new mw.Api().saveOption(
			'growthexperiments-tour-homepage-welcome',
			'1',
		);
	}

	const returnToHomepageTour = new gt.TourBuilder( {
		name: 'homepage_return',
		isSinglePage: true,
		shouldLog: true,
	} );
	returnToHomepageTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
		name: 'welcome',
		title: mw.message( 'growthexperiments-tour-return-to-homepage' )
			.params( [ mw.user ] )
			.parse(),
		description: '',
		attachTo: '#pt-userpage-2:visible, .vector-user-links #p-personal, #pt-userpage:visible',
		position: 'bottom',
		overlay: false,
		autoFocus: true,
		buttons: [ {
			action: 'end',
			namemsg: 'growthexperiments-tour-response-button-okay',
		} ],
		onShow: markTourAsSeen,
		onClose: markTourAsSeen,
	} ) );
	mw.guidedTour.launchTour( 'homepage_return' );
}( mw.guidedTour ) );
