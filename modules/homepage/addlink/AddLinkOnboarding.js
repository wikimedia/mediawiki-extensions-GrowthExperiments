module.exports = ( function () {
	'use strict';
	var AddLinkOnboardingDialog = require( './AddLinkOnboardingDialog.js' ),
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
	 */
	function showDialogIfEligible() {
		if ( !shouldShowOnboarding || !suggestedEditSession.onboardingNeedsToBeShown ) {
			return;
		}
		showDialogForSession();
	}

	// Only append window manager & construct dialog if onboarding should be shown
	if ( shouldShowOnboarding ) {
		windowManager = new OO.ui.WindowManager( { modal: true } );
		$( document.body ).append( windowManager.$element );

		windows[ dialogName ] = new AddLinkOnboardingDialog(
			{ hasSlideTransition: true },
			{ prefName: addLinkOnboardingPrefName }
		);
		windowManager.addWindows( windows );
		// In case onboarding is invoked from a different module
		mw.hook( 'growthExperiments.showAddLinkOnboardingIfNeeded' ).add( showDialogIfEligible );
	}

	return {
		showDialogIfEligible: showDialogIfEligible
	};
}() );
