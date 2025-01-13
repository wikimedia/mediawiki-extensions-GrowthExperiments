'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' );

describe( 'Homepage', () => {

	// Skipped on 2023-05-20 in 921608 because of T334626 and T337137
	it.skip( 'saves change tags for unstructured task edits made via VisualEditor', async () => {
		const copyeditArticle = 'Classical kemençe';
		await browser.execute( ( done ) => mw.loader.using( 'mediawiki.api' ).then( () => new mw.Api().saveOptions( {
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'copyedit' ] )
		} ).done( () => done() )
		) );

		await HomepagePage.open();
		await browser.waitUntil(
			async () => await HomepagePage.suggestedEditsCardTitle.getText() === copyeditArticle
		);
		await browser.waitUntil(
			async () => await HomepagePage.suggestedEditsCardUrl.waitForExist() &&
				await HomepagePage.suggestedEditsCardUrl.getAttribute( 'href' ) !== '#'
		);
		await HomepagePage.suggestedEditsCard.waitForClickable();
		await HomepagePage.suggestedEditsCard.click();

		await browser.setupInterceptor();
		await HomepagePage.editAndSaveArticle( 'first edit', true );
		await HomepagePage.runJobs( 'Calling runJobs.php for first edit' );
		assert.ok( HomepagePage.postEditDialog.isDisplayed() );

		// Get the revision ID of the change that was just made.
		let requests = await browser.getRequests();
		let savedRevId;
		requests.forEach( ( request ) => {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-copyedit' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		const username = await browser.execute( () => mw.user.getName() );
		let result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task copyedit', copyeditArticle, username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );

		// Follow-up edit.
		await HomepagePage.editAndSaveArticle( 'second edit' );
		await HomepagePage.runJobs( 'Calling runJobs.php for second edit' );
		assert.ok( HomepagePage.postEditDialog.isDisplayed() );

		requests = await browser.getRequests();
		requests.forEach( ( request ) => {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-copyedit' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task copyedit', copyeditArticle, username );
		let found = false;
		result.query.recentchanges.forEach( ( rc ) => {
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
		await HomepagePage.runJobs( 'Calling runJobs.php for third edit' );

		requests = await browser.getRequests();
		requests.forEach( ( request ) => {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-copyedit' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task copyedit', 'Cretan lyra', username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );
	} );

} );
