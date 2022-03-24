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
		await browser.waitUntil( async () => {
			return await HomepagePage.suggestedEditsCardUrl.waitForExist() &&
				await HomepagePage.suggestedEditsCardUrl.getAttribute( 'href' ) !== '#';
		} );
		await HomepagePage.suggestedEditsCard.waitForClickable();
		await HomepagePage.suggestedEditsCard.click();

		await browser.setupInterceptor();
		await HomepagePage.editAndSaveArticle( 'first edit', true );
		await HomepagePage.rebuildRecentChanges( 'Rebuilding recent changes for first edit' );
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
		await HomepagePage.rebuildRecentChanges( 'Rebuilding recent changes for second edit' );
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

		// click the suggestion in the post-edit dialog, edit once more.
		await HomepagePage.waitForPostEditDialog();

		await HomepagePage.postEditDialogSmallTaskCard.click();

		// Set up the interceptor again, as we're on a new page.
		await browser.setupInterceptor();
		await HomepagePage.editAndSaveArticle( 'third edit', true );
		await HomepagePage.rebuildRecentChanges( 'Rebuilding recent changes for third edit' );

		requests = await browser.getRequests();
		requests.forEach( function ( request ) {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-copyedit' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task copyedit', 'Cretan lyra', username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );
	} );

	it( 'Shows a suggested edits card and allows navigation forwards and backwards through queue', async () => {
		await HomepagePage.open();
		await assert( HomepagePage.suggestedEditsCard.isExisting() );
		await HomepagePage.assertCardTitleIs( 'Classical kemençe' );
		await HomepagePage.waitForInteractiveTaskFeed();
		// Previous button should be disabled when on first card.
		await HomepagePage.assertPreviousButtonIsDisabled();
		await HomepagePage.advanceToNextCard();
		await HomepagePage.assertCardTitleIs( 'Cretan lyra' );
		await HomepagePage.goBackToPreviousCard();
		// Previous button should still be disabled on first card.
		await HomepagePage.assertPreviousButtonIsDisabled();
		await HomepagePage.advanceToNextCard();
		// Go to the end of queue card.
		await HomepagePage.advanceToNextCard();
		await HomepagePage.assertCardTitleIs( 'No more suggestions' );
		// Users should not be able to navigate past the end of queue card.
		await HomepagePage.assertNextButtonIsDisabled();
	} );

} );
