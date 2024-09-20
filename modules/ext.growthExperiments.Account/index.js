( function () {
	if ( mw.config.get( 'welcomesurvey' ) ) {
		const WelcomeSurvey = require( './WelcomeSurvey.js' );
		WelcomeSurvey.setupLanguageSelector();

	} else if ( mw.config.get( 'confirmemail' ) ) {
		const ConfirmEmail = require( './ConfirmEmail.js' );
		ConfirmEmail.maybeShowWarning();
	}
}() );
