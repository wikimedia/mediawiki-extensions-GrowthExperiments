( function () {
	if ( mw.config.get( 'welcomesurvey' ) ) {
		const WelcomeSurvey = require( './WelcomeSurvey.js' );
		WelcomeSurvey.setupLanguageSelector();

	} else if ( mw.config.get( 'confirmemail' ) ) {
		const ConfirmEmail = require( './ConfirmEmail.js' );
		ConfirmEmail.maybeShowWarning();
	}
	if ( mw.config.get( 'GECreateAccountExperimentV1' ) ) {
		// T415659
		const WE18ExperimentV1 = require( './WE18ExperimentV1.js' );
		const enhancer = new WE18ExperimentV1();
		enhancer.enhanceUsernameInputWithNameAdjustment();
	}
}() );
