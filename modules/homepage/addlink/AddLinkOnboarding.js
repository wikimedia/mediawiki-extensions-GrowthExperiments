module.exports = ( function () {
	'use strict';
	var AddLinkOnboardingDialog = require( './AddLinkOnboardingDialog.js' ),
		dialogName = 'addLinkOnboardingDialog',
		addLinkOnboardingPrefName = 'growthexperiments-addlink-onboarding',
		shouldShowOnboarding = !mw.user.options.get( addLinkOnboardingPrefName ),
		windows = {},
		windowManager;

	// Only append window manager & construct dialog if onboarding should be shown
	if ( shouldShowOnboarding ) {
		windowManager = new OO.ui.WindowManager( { modal: true } );
		$( document.body ).append( windowManager.$element );

		windows[ dialogName ] = new AddLinkOnboardingDialog(
			{ hasSlideTransition: true },
			{ prefName: addLinkOnboardingPrefName }
		);
		windowManager.addWindows( windows );
	}

	return {
		showDialogIfEligible: function () {
			if ( shouldShowOnboarding ) {
				windowManager.openWindow( dialogName );
			}
		}
	};
}() );
