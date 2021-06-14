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

	it( 'Shows a suggested edits card and allows navigation forwards and backwards through queue', () => {
		HomepagePage.open();
		assert( HomepagePage.suggestedEditsCard.isExisting() );
		assert.strictEqual( HomepagePage.suggestedEditsCardTitle.getText(), 'Douglas Adams' );
		// The previous/next buttons start out as disabled, and then are switched to
		// enabled/disabled depending on where in the task queue the user is.
		browser.waitUntil( () => {
			return HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ) === 'false';
		} );
		assert.strictEqual( HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'true' );
		HomepagePage.suggestedEditsNextButton.waitForClickable();
		assert.strictEqual( HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'false' );
		HomepagePage.suggestedEditsNextButton.click();
		assert.strictEqual( HomepagePage.suggestedEditsCardTitle.getText(), 'The Hitchhiker\'s Guide to the Galaxy' );
		browser.waitUntil( () => {
			return HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ) === 'false';
		} );
		assert.strictEqual( HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'false' );
		// TODO: Fix in T283546
		// assert.strictEqual( HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'true' );
	} );

} );
