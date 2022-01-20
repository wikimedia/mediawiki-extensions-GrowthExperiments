( function () {
	if ( mw.config.get( 'welcomesurvey' ) ) {
		var WelcomeSurvey = require( './WelcomeSurvey.js' );
		WelcomeSurvey.setupLanguageSelector();

	} else if ( mw.config.get( 'confirmemail' ) ) {
		var ConfirmEmail = require( './ConfirmEmail.js' );
		ConfirmEmail.maybeShowWarning();
	}
}() );
