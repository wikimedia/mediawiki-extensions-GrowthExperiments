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

	async waitForLinkInspector() {
		await this.waitForDisplayedAndClickable( this.linkInspector );
	}

	acceptSuggestion() {
		return this.clickButton( this.yesButton );
	}

	skipSuggestion() {
		return this.clickButton( this.nextButton );
	}

	rejectSuggestion() {
		return this.clickButton( this.noButton );
	}

	closeRejectionDialog() {
		return this.clickButton( this.rejectionDialogDoneButton );
	}

	clickPublishChangesButton() {
		return this.clickButton( this.publishButton );
	}

	async saveChangesToArticle() {
		await this.waitForDisplayedAndClickable( this.saveDialog );
		return this.clickButton( this.saveChangesButton );
	}

	async clickButton( button ) {
		await this.waitForDisplayedAndClickable( button );
		return button.click();
	}

	async waitForDisplayedAndClickable( element ) {
		await element.waitForClickable( { timeout: 30000 } );
		await element.waitForDisplayed( { timeout: 30000 } );
	}

}

module.exports = new AddLinkArticlePage();
