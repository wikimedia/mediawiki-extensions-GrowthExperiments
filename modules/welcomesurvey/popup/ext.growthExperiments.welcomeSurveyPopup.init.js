( function () {

	var WelcomeSurveyDialog = require( './ui/ext.growthExperiments.WelcomeSurveyDialog.js' ),
		WelcomeSurveyConfirmationDialog = require( './ui/ext.growthExperiments.WelcomeSurveyConfirmationDialog.js' ),
		windowManager = new OO.ui.WindowManager(),
		survey = new WelcomeSurveyDialog( {
			group: mw.config.get( 'wgWelcomeSurveyExperimentalGroup' ),
			questionsConfig: mw.config.get( 'wgWelcomeSurveyQuestions' ),
			privacyStatementUrl: mw.config.get( 'wgWelcomeSurveyPrivacyPolicyUrl' )
		} ),
		confirmation = new WelcomeSurveyConfirmationDialog( {
			privacyStatementUrl: mw.config.get( 'wgWelcomeSurveyPrivacyPolicyUrl' )
		} );

	// eslint-disable-next-line no-jquery/no-global-selector
	$( 'body' ).append( windowManager.$element );
	windowManager.addWindows( [ survey, confirmation ] );
	windowManager.openWindow( survey );

}() );
