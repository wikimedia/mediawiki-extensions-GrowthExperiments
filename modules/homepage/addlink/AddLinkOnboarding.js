module.exports = ( function () {
	'use strict';
	var AddLinkOnboardingDialog = require( './AddLinkOnboardingDialog.js' ),
		LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' ),
		dialogName = 'addLinkOnboardingDialog',
		addLinkOnboardingPrefName = 'growthexperiments-addlink-onboarding',
		shouldShowOnboarding = !mw.user.options.get( addLinkOnboardingPrefName ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		windows = {},
		windowManager;

	/**
	 * Show onboarding dialog and update onboardingNeedsToBeShown in suggestedEditSession
	 *
	 * This prevents onboarding from being shown multiple times within a session.
	 */
	function showDialogForSession() {
		suggestedEditSession.onboardingNeedsToBeShown = false;
		suggestedEditSession.save();
		windowManager.openWindow( dialogName );
	}

	/**
	 * Show onboarding dialog if it hasn't been shown in the session and
	 * if the user hasn't checked "Don't show again"
	 *
	 * If the user has completed onboarding, fire an event so that actions
	 * that need to happen after onboarding can be invoked.
	 */
	function showDialogIfEligible() {
		if ( shouldShowOnboarding && suggestedEditSession.onboardingNeedsToBeShown ) {
			showDialogForSession();
		} else {
			mw.hook( 'growthExperiments.addLinkOnboardingCompleted' ).fire();
		}
	}

	// Only append window manager & construct dialog if onboarding should be shown
	if ( shouldShowOnboarding ) {
		windowManager = new OO.ui.WindowManager( { modal: true } );
		$( document.body ).append( windowManager.$element );

		windows[ dialogName ] = new AddLinkOnboardingDialog(
			{
				hasSlideTransition: true,
				logger: new LinkSuggestionInteractionLogger( {
					// eslint-disable-next-line camelcase
					is_mobile: OO.ui.isMobile()
				} )
			},
			{ prefName: addLinkOnboardingPrefName }
		);
		windowManager.addWindows( windows );
	}

	// In case onboarding is invoked from a different module
	mw.hook( 'growthExperiments.showAddLinkOnboardingIfNeeded' ).add( showDialogIfEligible );

	return {
		showDialogIfEligible: showDialogIfEligible
	};
}() );
