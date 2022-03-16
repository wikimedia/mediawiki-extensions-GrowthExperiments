'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddLinkArticlePage = require( '../pageobjects/addlink.article.page' );

describe( 'add link', function () {

	it( 'link inspector can be used to accept/reject links and save an article.', async function () {
		const addlinkArticle = 'Douglas Adams';
		if ( !isQuibbleUsingApache ) {
			this.skip( 'This test depends on using PHP-FPM and Apache as the backend.' );
		}
		await AddLinkArticlePage.insertLinkRecommendationsToDatabase();

		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		await browser.execute( function () {
			return new mw.Api().saveOptions( {
				'growthexperiments-addlink-onboarding': 1
			} );
		} );
		await HomepagePage.open();
		assert.strictEqual( await HomepagePage.suggestedEditsCardTitle.getText(), addlinkArticle );

		await HomepagePage.suggestedEditsCard.waitForDisplayed();
		await HomepagePage.suggestedEditsCard.waitForClickable( { timeout: 30000 } );
		await HomepagePage.suggestedEditsCard.click();

		await AddLinkArticlePage.waitForLinkInspector();

		const currentProgress = await AddLinkArticlePage.progressTitle.getText();
		await AddLinkArticlePage.acceptSuggestion();
		// FIXME: If we don't wait for the progress title to change, the "accept"
		// isn't registered when the article is saved. That seems like an application bug.
		await AddLinkArticlePage.waitForProgressToNextSuggestion( currentProgress );

		await AddLinkArticlePage.rejectSuggestion();
		await AddLinkArticlePage.closeRejectionDialog();

		await AddLinkArticlePage.clickPublishChangesButton();

		await browser.setupInterceptor();
		await AddLinkArticlePage.saveChangesToArticle();

		await Util.waitForModuleState( 'ext.growthExperiments.PostEdit' );

		await HomepagePage.waitForPostEditDialog();

		await HomepagePage.rebuildRecentChanges();

		// Get the revision ID of the change that was just made.
		const requests = await browser.getRequests();
		let savedRevId;
		requests.forEach( function ( request ) {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-link-recommendation' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
				assert.deepEqual( request.response.body.visualeditoredit.gechangetags[ 0 ], [ 'newcomer task', 'newcomer task add link' ] );
			}
		} );

		const username = await browser.execute( function () {
			return mw.user.getName();
		} );
		let result;
		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task add link', addlinkArticle, username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );

		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task', addlinkArticle, username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );
	} );

} );
