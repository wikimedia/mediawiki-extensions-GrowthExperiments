'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddLinkArticlePage = require( '../pageobjects/addlink.article.page' );

describe( 'add link', () => {

	it( 'link inspector can be used to accept/reject links and save an article.', async function () {
		const addlinkArticle = 'Douglas Adams';
		if ( !isQuibbleUsingApache ) {
			this.skip( 'This test depends on using PHP-FPM and Apache as the backend.' );
		}
		await AddLinkArticlePage.insertLinkRecommendationsToDatabase();

		await HomepagePage.open();
		assert.strictEqual( await HomepagePage.suggestedEditsCardTitle.getText(), addlinkArticle );

		await HomepagePage.suggestedEditsCard.waitForDisplayed();
		await HomepagePage.suggestedEditsCard.waitForClickable( { timeout: 30000 } );
		await HomepagePage.suggestedEditsCard.click();

		await AddLinkArticlePage.onboardingDialog.waitForDisplayed( { timeout: 30000 } );
		await AddLinkArticlePage.closeOnboardingDialog();

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

		await AddLinkArticlePage.waitForPostEditNextSuggestedTask();

		await HomepagePage.runJobs();

		// Get the revision ID of the change that was just made.
		const requests = await browser.getRequests();
		let savedRevId;
		requests.forEach( ( request ) => {
			if ( request.method === 'POST' && request.body && request.body[ 'data-ge-task-link-recommendation' ] ) {
				savedRevId = request.response.body.visualeditoredit.newrevid;
			}
		} );

		const username = await browser.execute( () => mw.user.getName() );
		let result;
		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task add link', addlinkArticle, username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );

		result = await HomepagePage.waitUntilRecentChangesItemExists( 'newcomer task', addlinkArticle, username );
		assert.strictEqual( result.query.recentchanges[ 0 ].revid, savedRevId );
	} );

} );
