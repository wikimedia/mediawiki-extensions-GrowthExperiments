( function () {
	if ( mw.config.get( 'welcomesurvey' ) ) {
		const WelcomeSurvey = require( './WelcomeSurvey.js' );
		WelcomeSurvey.setupLanguageSelector();
	}
}() );
