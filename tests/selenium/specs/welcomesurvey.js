'use strict';

const assert = require( 'assert' ),
	SpecialWelcomeSurveyPage = require( '../pageobjects/specialwelcomesurvey.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' );

describe( 'Special:WelcomeSurvey', function () {

	// T233263
	it( 'stores responses for users with NONE group when survey is submitted', async function () {
		await browser.deleteAllCookies();
		const username = Util.getTestString( 'User-' );
		const password = Util.getTestString();
		await CreateAccountPage.createAccount( username, password );
		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		await SpecialWelcomeSurveyPage.finishButtonSelector.waitForExist();
		await SpecialWelcomeSurveyPage.finish.click();
		Util.waitForModuleState( 'mediawiki.user', 'ready', 5000 );
		Util.waitForModuleState( 'mediawiki.base', 'ready', 5000 );
		const responses = await browser.execute( async function () {
			return JSON.parse( await mw.user.options.get( 'welcomesurvey-responses' ) );
		} );
		assert.strictEqual( responses.reason, 'placeholder' );
		// eslint-disable-next-line no-underscore-dangle
		assert.strictEqual( responses._group, 'exp2_target_specialpage' );
	} );
} );
