'use strict';

const assert = require( 'assert' ),
	SpecialWelcomeSurveyPage = require( '../pageobjects/specialwelcomesurvey.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' );

describe( 'Special:WelcomeSurvey', function () {

	// T233263
	it( 'stores responses for users with NONE group when survey is submitted', function () {
		UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
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
		SpecialWelcomeSurveyPage.finishButtonSelector.waitForExist();
		SpecialWelcomeSurveyPage.finish.click();
		const responses = browser.execute( function () {
			return JSON.parse( mw.user.options.get( 'welcomesurvey-responses' ) );
		} );
		assert.strictEqual( responses.reason, 'placeholder' );
		// eslint-disable-next-line no-underscore-dangle
		assert.strictEqual( responses._group, 'exp2_target_specialpage' );
	} );
} );
