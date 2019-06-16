var assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	PreferencesPage = require( '../pageobjects/preferences.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' );

describe( 'Homepage', function () {

	it( 'can be enabled', function () {

		UserLoginPage.login( browser.options.username, browser.options.password );
		PreferencesPage.open();

		browser.execute( ( homepage ) => homepage.scrollIntoView(), browser.element( '[name="wpgrowthexperiments-homepage-enable"]' ).value );
		PreferencesPage.homepage.waitForVisible();
		PreferencesPage.homepage.click();
		PreferencesPage.save.click();
		HomepagePage.open();

		assert( HomepagePage.homepage.isExisting() );

	} );

} );
