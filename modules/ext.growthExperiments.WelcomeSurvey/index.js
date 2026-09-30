( function () {
	const sendOnboardingReadyEvent = require( '../utils/sendOnboardingReadyEvent.js' );

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

		if ( !mw.config.get( 'isEarlyOnboardingExperimentControl' ) ) {
			return;
		}

		// The form works before JS runs, so the first paint is when the user can act.
		const firstContentfulPaintPromise = new Promise( ( resolve ) => {
			new PerformanceObserver( ( list, observer ) => {
				const firstContentfulPaint = list.getEntriesByName( 'first-contentful-paint' )[ 0 ];
				if ( firstContentfulPaint ) {
					observer.disconnect();
					resolve( firstContentfulPaint.startTime );
				}
			} ).observe( { type: 'paint', buffered: true } );
		} );
		firstContentfulPaintPromise.then( ( readyMs ) => {
			mw.track(
				'stats.mediawiki_GrowthExperiments_onboarding_ready_seconds',
				readyMs,
				{
					group: 'control',
					platform: mw.config.get( 'skin' ) === 'minerva' ? 'mobile' : 'desktop',
					wiki: mw.config.get( 'wgDBname' ),
				},
			);
		} );

		mw.loader.using( [ 'ext.testKitchen', 'ext.wikimediaEvents.testKitchen' ] ).then( async () => {
			const experiment = await mw.tk.getExperiment( 'de-1-3-1-specialhomepage-onboarding-ab-test' );
			firstContentfulPaintPromise.then( ( readyMs ) => {
				sendOnboardingReadyEvent( experiment, readyMs );
			} );

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
