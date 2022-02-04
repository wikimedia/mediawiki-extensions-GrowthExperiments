'use strict';

const assert = require( 'assert' ),
	HomepagePage = require( '../pageobjects/homepage.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddLinkArticlePage = require( '../pageobjects/addlink.article.page' );

describe( 'add link', function () {

	it( 'link inspector appears after clicking through task from Special:Homepage', function () {
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
		HomepagePage.suggestedEditsCard.waitForClickable();
		HomepagePage.suggestedEditsCard.click();
		AddLinkArticlePage.linkInspector.waitForDisplayed( { timeout: 30000 } );
		AddLinkArticlePage.linkInspector.waitForClickable( { timeout: 20000 } );
		AddLinkArticlePage.yesButton.waitForDisplayed();
		AddLinkArticlePage.yesButton.click();
		// TODO: Wait for "Yes" to be toggled on before advancing.
		AddLinkArticlePage.nextButton.click();
		AddLinkArticlePage.nextButton.waitForDisplayed();
		AddLinkArticlePage.nextButton.click();
		AddLinkArticlePage.noButton.click();
		AddLinkArticlePage.rejectionDialogDoneButton.waitForDisplayed();
		AddLinkArticlePage.rejectionDialogDoneButton.click();
		AddLinkArticlePage.publishButton.click();
		// TODO: Make some assertion on what is presented in the publish dialog.
	} );

} );
