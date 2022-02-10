'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddLinkArticlePage = require( '../pageobjects/addlink.article.page' );

describe( 'add link', function () {

	it( 'link inspector can be used to accept/reject links and save an article.', function () {
		if ( !isQuibbleUsingApache ) {
			this.skip( 'This test depends on using PHP-FPM and Apache as the backend.' );
		}
		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		browser.execute( async () => {
			return new mw.Api().saveOptions( {
				'growthexperiments-addlink-onboarding': 1
			} );
		} );
		HomepagePage.open();
		assert.strictEqual( HomepagePage.suggestedEditsCardTitle.getText(), 'Douglas Adams' );

		HomepagePage.suggestedEditsCard.waitForDisplayed();
		HomepagePage.suggestedEditsCard.waitForClickable( { timeout: 30000 } );
		HomepagePage.suggestedEditsCard.click();

		AddLinkArticlePage.waitForLinkInspector();

		AddLinkArticlePage.acceptSuggestion();

		AddLinkArticlePage.rejectSuggestion();
		AddLinkArticlePage.closeRejectionDialog();

		AddLinkArticlePage.clickPublishChangesButton();

		AddLinkArticlePage.saveChangesToArticle();
	} );

} );
