( function () {

	if ( mw.config.get( 'welcomesurvey' ) ) {
		const WelcomeSurvey = require( './WelcomeSurvey.js' );
		try {
			const languageSelectorWidgetInstance = WelcomeSurvey.setupLanguageSelector();
			instrumentWelcomeSurvey( languageSelectorWidgetInstance );
		} catch ( e ) {
			mw.errorLogger.logError(
				new Error( 'WelcomeSurvey LanguageSelector is unavailable' ),
				'error.GrowthExperiments',
			);
			instrumentWelcomeSurvey();
		}
	}

	function instrumentWelcomeSurvey( languageSelectorWidgetInstance = null ) {
		mw.loader.using( [ 'ext.testKitchen', 'ext.wikimediaEvents.testKitchen' ] ).then( async () => {
			const experiment = await mw.tk.getExperiment( 'de-1-3-1-specialhomepage-onboarding-aa-test' );
			experiment.sendExposure();

			let started = false;
			function onFirstChange() {
				if ( started ) {
					return;
				}
				started = true;
				experiment.send( 'welcome_survey_account_setup_started' );
			}

			// eslint-disable-next-line no-jquery/no-global-selector
			$( '#mw-input-reason, #mw-input-wpedited, #mw-input-wpemail' ).each( ( i, el ) => {
				let widget;
				try {
					widget = OO.ui.infuse( el );
				} catch ( e ) {
					return; // not an infusable widget element
				}

				if ( widget instanceof OO.ui.InputWidget ) {
					widget.on( 'change', onFirstChange );
				}
			} );

			const { ClickThroughRateInstrument } = require( 'ext.wikimediaEvents.testKitchen' );
			ClickThroughRateInstrument.start( 'button[name=save]', 'WelcomeSurvey/AccountSetup save button', experiment );
			ClickThroughRateInstrument.start( 'button[name=skip]', 'WelcomeSurvey/AccountSetup skip button', experiment );

			if ( languageSelectorWidgetInstance ) {
				languageSelectorWidgetInstance.on( 'change', onFirstChange );
			}
		} );
	}
}() );
