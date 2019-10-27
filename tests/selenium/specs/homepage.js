var assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	PreferencesPage = require( '../pageobjects/preferences.page' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Api = require( 'wdio-mediawiki/Api' ),
	Util = require( 'wdio-mediawiki/Util' );

describe( 'Homepage', function () {

	beforeEach( function () {
		var username = Util.getTestString( 'NewUser-' );
		var password = Util.getTestString();
		browser.call( function () {
			return Api.createAccount( username, password );
		} );
		LoginPage.login( username, password );
	} );

	it( 'is enabled for new user', function () {

		HomepagePage.open();
		assert( HomepagePage.homepage.isExisting() );

	} );

	it( 'can be disabled for new user', function () {

		PreferencesPage.open();
		PreferencesPage.clickHomepageCheckBox();

		HomepagePage.open();
		assert( !HomepagePage.homepage.isExisting() );

	} );

} );
