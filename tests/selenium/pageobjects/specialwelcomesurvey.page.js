'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class SpecialWelcomeSurveyPage extends Page {
	get specialwelcomesurvey() { return $( '#welcome-survey-form' ); }
	get finish() { return $( "button[value='Finish']" ); }
	get finishButtonSelector() { return $( "button[value='Finish']" ); }

	open() {
		super.openTitle( 'Special:WelcomeSurvey', { _group: 'control' } );
	}

}

module.exports = new SpecialWelcomeSurveyPage();
