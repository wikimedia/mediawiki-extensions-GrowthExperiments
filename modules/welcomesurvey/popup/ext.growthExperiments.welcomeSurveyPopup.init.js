( function () {

	var windowManager = new OO.ui.WindowManager(),
		survey = new mw.libs.ge.WelcomeSurvey.WelcomeSurveyDialog( {
			group: mw.config.get( 'wgWelcomeSurveyExperimentalGroup' ),
			questionsConfig: mw.config.get( 'wgWelcomeSurveyQuestions' ),
			privacyStatementUrl: mw.config.get( 'wgWelcomeSurveyPrivacyPolicyUrl' )
		} ),
		confirmation = new mw.libs.ge.WelcomeSurvey.WelcomeSurveyConfirmationDialog( {
			privacyStatementUrl: mw.config.get( 'wgWelcomeSurveyPrivacyPolicyUrl' )
		} );

	$( 'body' ).append( windowManager.$element );
	windowManager.addWindows( [ survey, confirmation ] );
	windowManager.openWindow( survey );

}() );
