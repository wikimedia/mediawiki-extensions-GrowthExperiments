'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	PreferencesPage = require( '../pageobjects/preferences.page' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Api = require( 'wdio-mediawiki/Api' ),
	Util = require( 'wdio-mediawiki/Util' );

describe( 'Homepage', function () {
	let password, username, bot;

	before( async () => {
		bot = await Api.bot();
	} );

	beforeEach( function () {
		username = Util.getTestString( 'NewUser-' );
		password = Util.getTestString();
		browser.call( async () => {
			await Api.createAccount( bot, username, password );
		} );
		LoginPage.login( username, password );
	} );

	it( 'is enabled for new user', function () {

		HomepagePage.open();
		assert( HomepagePage.homepage.isExisting() );

	} );

	it.skip( 'can be disabled for new user', function () {

		PreferencesPage.open();
		PreferencesPage.clickHomepageCheckBox();

		HomepagePage.open();
		assert( !HomepagePage.homepage.isExisting() );

	} );

	it.skip( 'can be disabled and re-enabled for new user', function () {

		PreferencesPage.open();
		PreferencesPage.clickHomepageCheckBox();

		HomepagePage.open();
		assert( !HomepagePage.homepage.isExisting() );

		PreferencesPage.open();
		PreferencesPage.clickHomepageCheckBox();

		HomepagePage.open();
		assert( HomepagePage.homepage.isExisting() );

	} );

	it( 'Heading shows the logged-in user\'s name', function () {

		HomepagePage.open();
		assert( HomepagePage.firstheading.getText(), `Hello, ${username}!` );
	} );

} );
