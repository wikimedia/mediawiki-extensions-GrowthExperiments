'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' );

describe( 'Homepage', function () {

	it( 'is enabled for new user', function () {
		HomepagePage.open();
		assert( HomepagePage.homepage.isExisting() );
	} );

	it( 'Heading shows the logged-in user\'s name', function () {
		HomepagePage.open();
		const username = browser.execute( function () {
			return mw.user.getName();
		} );
		assert( HomepagePage.firstheading.getText(), `Hello, ${username}!` );
	} );

} );
