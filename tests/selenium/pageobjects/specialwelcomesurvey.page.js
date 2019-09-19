const Page = require( 'wdio-mediawiki/Page' );

class SpecialWelcomeSurveyPage extends Page {
	get specialwelcomesurvey() { return browser.element( '#welcome-survey-form' ); }
	get finish() { return browser.element( this.finishButtonSelector ); }
	get finishButtonSelector() { return "button[value='Finish']"; }

	open() {
		super.openTitle( 'Special:WelcomeSurvey', { _group: 'exp2_target_specialpage' } );
	}

}

module.exports = new SpecialWelcomeSurveyPage();
