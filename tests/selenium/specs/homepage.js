'use strict';

const assert = require( 'assert' ),
	Util = require( 'wdio-mediawiki/Util' ),
	HomepagePage = require( '../pageobjects/homepage.page' );

describe( 'Homepage', function () {

	it( 'is enabled for new user', async function () {
		await HomepagePage.open();
		assert( HomepagePage.homepage.isExisting() );
	} );

	it( 'Heading shows the logged-in user\'s name', async function () {
		await HomepagePage.open();
		Util.waitForModuleState( 'mediawiki.base', 'ready', 5000 );
		const username = browser.execute( function () {
			return mw.user.getName();
		} );
		assert( HomepagePage.firstheading.getText(), `Hello, ${username}!` );
	} );

	it.skip( 'Shows a suggested edits card and allows navigation forwards and backwards through queue', async () => {
		await HomepagePage.open();
		assert( HomepagePage.suggestedEditsCard.isExisting() );
		assert.strictEqual( HomepagePage.suggestedEditsCardTitle.getText(), 'Douglas Adams' );
		// The previous/next buttons start out as disabled, and then are switched to
		// enabled/disabled depending on where in the task queue the user is.
		await browser.waitUntil( async () => {
			return await HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ) === 'false';
		} );
		assert.strictEqual( HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'true' );
		await HomepagePage.suggestedEditsNextButton.waitForClickable();
		assert.strictEqual( HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'false' );
		await HomepagePage.suggestedEditsNextButton.click();
		await browser.waitUntil( async () => {
			return await HomepagePage.suggestedEditsCardTitle.getText() === 'The Hitchhiker\'s Guide to the Galaxy';
		} );
		assert.strictEqual( HomepagePage.suggestedEditsCardTitle.getText(), 'The Hitchhiker\'s Guide to the Galaxy' );
		// Go back to first card and check that previous button is disabled.
		await HomepagePage.suggestedEditsPreviousButton.click();
		assert.strictEqual( HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'true' );
		// Go forwards again.
		await HomepagePage.suggestedEditsNextButton.click();
		await browser.waitUntil( async () => {
			return await HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ) === 'false';
		} );
		assert.strictEqual( HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'false' );
		assert.strictEqual( HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'false' );
		// Go to the end of queue card.
		await HomepagePage.suggestedEditsNextButton.click();
		assert.strictEqual( HomepagePage.suggestedEditsCardTitle.getText(), 'No more suggestions' );
		assert.strictEqual( HomepagePage.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'false' );
		assert.strictEqual( HomepagePage.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'true' );
	} );

} );
