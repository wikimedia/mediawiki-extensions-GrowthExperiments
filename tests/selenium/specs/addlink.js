'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddLinkArticlePage = require( '../pageobjects/addlink.article.page' );

describe( 'add link', function () {

	it( 'link inspector can be used to accept/reject links and save an article.', async function () {
		if ( !isQuibbleUsingApache ) {
			this.skip( 'This test depends on using PHP-FPM and Apache as the backend.' );
		}
		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		await browser.execute( function () {
			return new mw.Api().saveOptions( {
				'growthexperiments-addlink-onboarding': 1
			} );
		} );
		await HomepagePage.open();
		assert.strictEqual( await HomepagePage.suggestedEditsCardTitle.getText(), 'Douglas Adams' );

		await HomepagePage.suggestedEditsCard.waitForDisplayed();
		await HomepagePage.suggestedEditsCard.waitForClickable( { timeout: 30000 } );
		await HomepagePage.suggestedEditsCard.click();

		await AddLinkArticlePage.waitForLinkInspector();

		await AddLinkArticlePage.acceptSuggestion();

		await AddLinkArticlePage.rejectSuggestion();
		await AddLinkArticlePage.closeRejectionDialog();

		await AddLinkArticlePage.clickPublishChangesButton();

		await AddLinkArticlePage.saveChangesToArticle();
	} );

} );
