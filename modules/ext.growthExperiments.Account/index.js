( function () {
	if ( mw.config.get( 'welcomesurvey' ) ) {
		const WelcomeSurvey = require( './WelcomeSurvey.js' );
		WelcomeSurvey.setupLanguageSelector();
	}
	if ( mw.config.get( 'GECreateAccountExperimentV2' ) ) {
		// T422295
		const WE18ExperimentV1 = require( './WE18ExperimentV1.js' );
		const enhancer = new WE18ExperimentV1();
		enhancer.enhanceUsernameInputWithNameAdjustment();
	}
}() );
