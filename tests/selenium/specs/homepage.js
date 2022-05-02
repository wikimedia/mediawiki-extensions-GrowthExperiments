'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' );

describe( 'Homepage', function () {

	it( 'saves change tags for unstructured task edits made via VisualEditor', async function () {
		const copyeditArticle = 'Classical kemençe';
		await browser.execute( ( done ) =>
			mw.loader.using( 'mediawiki.api' ).then( () =>
				new mw.Api().saveOptions( {
					'growthexperiments-homepage-se-filters': JSON.stringify( [ 'copyedit' ] )
				} ).done( () => done() )
			) );

		await HomepagePage.open();
		await browser.waitUntil( async () => {
			return await HomepagePage.suggestedEditsCardTitle.getText() === copyeditArticle;
		} );
		await HomepagePage.suggestedEditsCard.click();

		await browser.setupInterceptor();
		await HomepagePage.editAndSaveArticle( 'first edit' );
		await HomepagePage.rebuildRecentChanges();
		assert.ok( HomepagePage.postEditDialog.isDisplayed() );

		// Get the revision ID of the change that was just made.
		let requests = await browser.getRequests();
		let savedRevId;
		requests.forEach( function ( request ) {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-copyedit' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		const username = await browser.execute( function () {
			return mw.user.getName();
		} );
		let result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task copyedit', copyeditArticle, username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );

		// Follow-up edit.
		await HomepagePage.editAndSaveArticle( 'second edit' );
		await HomepagePage.rebuildRecentChanges();
		assert.ok( HomepagePage.postEditDialog.isDisplayed() );

		requests = await browser.getRequests();
		requests.forEach( function ( request ) {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-copyedit' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task copyedit', copyeditArticle, username );
		let found = false;
		result.query.recentchanges.forEach( function ( rc ) {
			if ( rc.revid === savedRevId ) {
				found = true;
			}
		} );
		assert.ok( found );
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
