var assert = require( 'assert' ),
	SpecialWelcomeSurveyPage = require( '../pageobjects/specialwelcomesurvey.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' );

describe( 'Special:WelcomeSurvey', function () {

	it( 'requires login', function () {
		SpecialWelcomeSurveyPage.open();
		assert( !SpecialWelcomeSurveyPage.specialwelcomesurvey.isExisting() );
	} );

	// T233263
	it( 'stores responses for users with NONE group when survey is submitted', function () {
		var responses;
		UserLoginPage.login( browser.options.username, browser.options.password );
		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		// Pretend the user was assigned to the control group.
		browser.execute( function () {
			return new mw.Api().saveOption( 'welcomesurvey-responses', JSON.stringify( {
				_group: 'NONE',
				// eslint-disable-next-line camelcase
				_render_date: '20190919100940'
			} ) );
		} );
		SpecialWelcomeSurveyPage.open();
		browser.waitForExist( SpecialWelcomeSurveyPage.finishButtonSelector );
		SpecialWelcomeSurveyPage.finish.click();
		responses = browser.execute( function () {
			return JSON.parse( mw.user.options.get( 'welcomesurvey-responses' ) );
		} );
		assert.strictEqual( responses.value.reason, 'placeholder' );
		// eslint-disable-next-line no-underscore-dangle
		assert.strictEqual( responses.value._group, 'exp2_target_specialpage' );
	} );
} );
