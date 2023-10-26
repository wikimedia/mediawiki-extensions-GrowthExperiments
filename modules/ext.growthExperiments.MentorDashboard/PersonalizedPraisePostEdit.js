( function () {
	'use strict';
	if ( mw.config.get( 'wgGEMentorDashboardPersonalizedPraisePostEdit' ) ) {
		mw.hook( 'postEdit' ).add( function () {
			mw.notify(
				$( '<span>' ).html(
					mw.message(
						'growthexperiments-mentor-dashboard-personalized-praise-send-appreciation-success',
						mw.config.get( 'wgGEMentorDashboardPersonalizedPraiseMenteeGender' )
					).parse()
				),
				{ type: 'success' }
			);

			// reset URL params and JS config variables
			window.history.replaceState( null, document.title, mw.util.getUrl( mw.config.get( 'wgRelevantPageName' ) ) );
			mw.config.set( 'wgGEMentorDashboardPersonalizedPraisePostEdit', false );
			mw.config.set( 'wgPostEditConfirmationDisabled', false );
		} );
	}
}() );
