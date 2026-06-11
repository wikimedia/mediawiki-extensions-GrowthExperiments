( function ( gt ) {
	const tourUtils = require( './tourUtils.js' ),
		isSuggestedEditsActivated = mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' );

	/**
	 * Update the user preference to indicate the tour has been seen.
	 */
	function markTourAsSeen() {
		new mw.Api().saveOption(
			'growthexperiments-tour-homepage-welcome',
			'1',
		);
	}

	let step;
	const welcomeTour = new gt.TourBuilder( {
		name: 'homepage_welcome',
		isSinglePage: true,
		shouldLog: true,
	} );
	if ( isSuggestedEditsActivated ) {
		step = welcomeTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			// TODO: Rename this message key because it's not variant C anymore
			description: mw.message( 'growthexperiments-tour-welcome-description-c' ).parse(),
			attachTo: '#pt-userpage-2:visible, .vector-user-links #p-personal, #pt-userpage:visible',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				// There is way to influence the button icon without terrible hacks,
				// so use the 'next' button which has the right icon but breaks the onclick
				// callback, and define a fake next step and use its onShow callback instead.
				action: 'next',
			} ],
			onShow: markTourAsSeen,
			onClose: markTourAsSeen,
		} ) );
		welcomeTour.step( {
			name: 'fake',
			description: 'also fake',
			onShow: function () {
				mw.guidedTour.endTour();
				mw.track( 'growthexperiments.startediting', {
					// The welcome dialog doesn't belong to any module
					moduleName: 'generic',
					trigger: 'welcome',
				} );
				// cancel displaying the guider
				return true;
			},
		} );
		step.next( 'fake' );
	} else {
		welcomeTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			// TODO: Rename this message key because it's not variant D anymore
			description: mw.message( 'growthexperiments-tour-welcome-description-d' ).parse(),
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
	}
	mw.guidedTour.launchTour( 'homepage_welcome' );
}( mw.guidedTour ) );
