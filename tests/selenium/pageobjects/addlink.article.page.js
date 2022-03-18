'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../../' );

class AddLinkArticlePage extends Page {

	get linkInspector() { return $( '.mw-ge-recommendedLinkToolbarDialog' ); }

	get yesButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-yes' ); }

	get noButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-no' ); }

	get nextButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-next' ); }

	get rejectionDialogDoneButton() { return $( '.mw-ge-recommendedLinkRejectionDialog .oo-ui-messageDialog-actions' ); }

	get publishButton() { return $( '.oo-ui-tool-name-machineSuggestionsSave ' ); }

	get saveDialog() { return $( '.ge-structuredTask-mwSaveDialog' ); }

	get saveChangesButton() { return $( '.ge-structuredTask-mwSaveDialog .oo-ui-processDialog-actions-primary' ); }

	get progressTitle() { return $( '.mw-ge-recommendedLinkToolbarDialog-progress-title' ); }

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

	async waitForProgressToNextSuggestion( oldProgress ) {
		await browser.waitUntil( async () => {
			return await this.progressTitle.getText() !== oldProgress;
		} );
	}

	async saveChangesToArticle() {
		await this.waitForDisplayedAndClickable( this.saveDialog );
		await this.waitForDisplayedAndClickable( this.saveChangesButton );
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

	async insertLinkRecommendationsToDatabase() {
		await childProcess.spawnSync(
			'php',
			[
				'extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/../fixtures/Douglas_Adams.suggestions.json' ),
				'--title=Douglas_Adams'
			],
			{ cwd: ip }
		);
		await childProcess.spawnSync(
			'php',
			[
				'extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/../fixtures/The_Hitchhikers_Guide_to_the_Galaxy.suggestions.json' ),
				'--title=The_Hitchhiker\'s_Guide_to_the_Galaxy'
			],
			{ cwd: ip }
		);
	}

}

module.exports = new AddLinkArticlePage();
