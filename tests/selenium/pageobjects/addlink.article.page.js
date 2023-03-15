'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	{ SevereServiceError } = require( 'webdriverio' ),
	ip = path.resolve( __dirname + '/../../../../../' );

class AddLinkArticlePage extends Page {

	get onboardingDialog() { return $( '.structuredtask-onboarding-dialog' ); }

	get linkInspector() { return $( '.mw-ge-recommendedLinkToolbarDialog' ); }

	get yesButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-yes' ); }

	get noButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-no' ); }

	get nextButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-next' ); }

	get rejectionDialogDoneButton() { return $( '.mw-ge-recommendedLinkRejectionDialog .oo-ui-messageDialog-actions' ); }

	get publishButton() { return $( '.oo-ui-tool-name-machineSuggestionsSave ' ); }

	get saveDialog() { return $( '.ge-structuredTask-mwSaveDialog' ); }

	get saveChangesButton() { return $( '.ge-structuredTask-mwSaveDialog .oo-ui-processDialog-actions-primary' ); }

	get progressTitle() { return $( '.mw-ge-recommendedLinkToolbarDialog-progress-title' ); }

	get skipOnboardingDialogButton() { return $( '.structuredtask-onboarding-dialog-skip-button' ); }

	get postEditDialogSmallTaskCard() { return $( '.mw-ge-postEditDrawer .mw-ge-small-task-card' ); }

	get postEditDialogSmallTaskCardTitle() { return $( '.mw-ge-small-task-card-title' ); }

	async waitForLinkInspector() {
		await this.waitForDisplayedAndClickable( this.linkInspector );
	}

	async closeOnboardingDialog() {
		return this.clickButton( this.skipOnboardingDialogButton );
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

	async waitForPostEditNextSuggestedTask() {
		await this.waitForDisplayedAndClickable( this.postEditDialogSmallTaskCard );
		await this.waitForDisplayedAndClickable( this.postEditDialogSmallTaskCardTitle );
		await browser.waitUntil( async () => {
			return await this.postEditDialogSmallTaskCardTitle.getText() === 'The Hitchhiker\'s Guide to the Galaxy';
		} );
	}

	async waitForDisplayedAndClickable( element ) {
		await element.waitForClickable( { timeout: 30000 } );
		await element.waitForDisplayed( { timeout: 30000 } );
	}

	async insertLinkRecommendationsToDatabase() {
		let insertLinkRecommendationsResult = await childProcess.spawnSync(
			'php',
			[
				'maintenance/run.php',
				'./extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/../fixtures/Douglas_Adams.suggestions.json' ),
				'--title=Douglas_Adams'
			],
			{ cwd: ip }
		);
		if ( insertLinkRecommendationsResult.status === 1 ) {
			console.log( String( insertLinkRecommendationsResult.stderr ) );
			throw new SevereServiceError( 'Unable to import Douglas_Adams.suggestions.json' );
		}
		insertLinkRecommendationsResult = await childProcess.spawnSync(
			'php',
			[
				'maintenance/run.php',
				'./extensions/GrowthExperiments/maintenance/insertLinkRecommendation.php',
				'--json-file=' + path.resolve( __dirname + '/../fixtures/The_Hitchhikers_Guide_to_the_Galaxy.suggestions.json' ),
				'--title=The_Hitchhiker\'s_Guide_to_the_Galaxy'
			],
			{ cwd: ip }
		);
		if ( insertLinkRecommendationsResult.status === 1 ) {
			console.log( String( insertLinkRecommendationsResult.stderr ) );
			throw new SevereServiceError( 'Unable to import The_Hitchhikers_Guide_to_the_Galaxy.suggestions.json' );
		}
	}

}

module.exports = new AddLinkArticlePage();
