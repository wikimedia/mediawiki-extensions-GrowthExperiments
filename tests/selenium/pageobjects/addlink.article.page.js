'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class AddLinkArticlePage extends Page {

	get linkInspector() { return $( '.mw-ge-recommendedLinkToolbarDialog' ); }

	get yesButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-yes' ); }

	get noButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-no' ); }

	get nextButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-next' ); }

	get rejectionDialogDoneButton() { return $( '.mw-ge-recommendedLinkRejectionDialog .oo-ui-messageDialog-actions' ); }

	get publishButton() { return $( '.oo-ui-tool-name-machineSuggestionsSave ' ); }

	get saveDialog() { return $( '.ge-structuredTask-mwSaveDialog' ); }

	get saveChangesButton() { return $( '.ge-structuredTask-mwSaveDialog .oo-ui-processDialog-actions-primary' ); }

	waitForLinkInspector() {
		this.waitForDisplayedAndClickable( this.linkInspector );
	}

	acceptSuggestion() {
		this.clickButton( this.yesButton );
	}

	skipSuggestion() {
		this.clickButton( this.nextButton );
	}

	rejectSuggestion() {
		this.clickButton( this.noButton );
	}

	closeRejectionDialog() {
		this.clickButton( this.rejectionDialogDoneButton );
	}

	clickPublishChangesButton() {
		this.clickButton( this.publishButton );
	}

	saveChangesToArticle() {
		this.waitForDisplayedAndClickable( this.saveDialog );
		this.clickButton( this.saveChangesButton );
	}

	clickButton( button ) {
		this.waitForDisplayedAndClickable( button );
		button.click();
	}

	waitForDisplayedAndClickable( element ) {
		element.waitForClickable( { timeout: 30000 } );
		element.waitForDisplayed( { timeout: 30000 } );
	}

}

module.exports = new AddLinkArticlePage();
