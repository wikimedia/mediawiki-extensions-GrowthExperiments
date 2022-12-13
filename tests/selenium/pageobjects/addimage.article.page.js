'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	fs = require( 'fs' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../../' ),
	Util = require( 'wdio-mediawiki/Util' ),
	HomepagePage = require( './homepage.page' );

class AddImageArticlePage extends Page {

	get imageInspector() { return $( '.mw-ge-recommendedImageToolbarDialog' ); }

	get yesButton() { return $( '.mw-ge-recommendedImageToolbarDialog-buttons-yes' ); }

	get noButton() { return $( '.mw-ge-recommendedImageToolbarDialog-buttons-no' ); }

	get publishButton() { return $( '.oo-ui-tool-name-machineSuggestionsSave ' ); }

	get saveDialog() { return $( '.ge-structuredTask-mwSaveDialog' ); }

	get saveChangesButton() { return $( '.ge-structuredTask-mwSaveDialog .oo-ui-processDialog-actions-primary' ); }

	get captionNode() { return $( '.ve-ce-activeNode mw-ge-recommendedImageCaption' ); }

	get captionNodePlaceholder() { return $( '.mw-ge-recommendedImageCaption-placeholder' ); }

	get imageInfoButton() { return $( '.mw-ge-recommendedImageToolbarDialog-details-button' ); }

	get imageDetailsButton() { return $( '.mw-ge-recommendedImage-detailsButton' ); }

	get messageDialogActionCloseButton() { return $( '.oo-ui-messageDialog-actions .oo-ui-buttonElement-button' ); }

	get captionHelpButton() { return $( '.mw-ge-recommendedImageCaption-help-button .oo-ui-buttonElement-button' ); }

	get messageDialogText() { return $( '.oo-ui-messageDialog-text' ); }

	get messageDialogMessage() { return $( '.oo-ui-messageDialog-message' ); }

	get addImageDetailsDialog() { return $( '.mw-ge-addImageDetailsDialog-fields' ); }

	get readButton() { return $( '#ca-view' ); }

	get discardEditsButton() { return $( '.ve-ui-overlay-global .oo-ui-flaggedElement-destructive' ); }

	async setup( articleTitle, useMobile ) {
		await this.setupSuggestions( articleTitle );
		Util.waitForModuleState( 'mediawiki.api', 'ready', 5000 );
		await browser.execute( function () {
			return new mw.Api().saveOptions( {
				'growthexperiments-addimage-onboarding': 1,
				'growthexperiments-addimage-caption-onboarding': 1
			} );
		} );
		await browser.execute( function () {
			return new mw.Api().saveOptions( {
				'growthexperiments-homepage-se-filters': JSON.stringify( [ 'image-recommendation' ] )
			} );
		} );
		let query = {};
		let fragment = '';
		if ( useMobile ) {
			query = { mobileaction: 'toggle_view_mobile' };
			fragment = '/homepage/suggested-edits';
		}
		await HomepagePage.open( query, fragment );
		expect( await HomepagePage.suggestedEditsCardTitle.getText() ).toEqual( articleTitle );

		await HomepagePage.suggestedEditsCard.waitForDisplayed();
		await HomepagePage.suggestedEditsCard.waitForClickable( { timeout: 30000 } );
		await HomepagePage.suggestedEditsCard.click();

		await this.waitForImageInspector();
	}

	async viewImageInfo() {
		await this.clickButton( this.imageInfoButton );
		await this.messageDialogMessage.waitForDisplayed();
		expect( await this.messageDialogMessage.getText() ).toContain( 'File:Mamoul biscotti libanesi.jpg' );
	}

	async closeImageInfo() {
		await this.clickButton( this.messageDialogActionCloseButton );
	}

	async viewImageDetails() {
		await this.clickButton( this.imageDetailsButton );
		await this.addImageDetailsDialog.waitForDisplayed();
		expect( await this.addImageDetailsDialog.getText() ).toContain( 'File:Mamoul biscotti libanesi.jpg' );
	}

	async closeImageDetails() {
		await this.clickButton( this.messageDialogActionCloseButton );
	}

	async switchToReadMode() {
		await this.clickButton( this.readButton );
	}

	async discardEdits() {
		await this.clickButton( this.discardEditsButton );
	}

	async waitForImageInspector() {
		await this.waitForDisplayedAndClickable( this.imageInspector );
	}

	async viewCaptionHelp() {
		// FIXME: Clicking on this caption help doesn't work.
		await this.clickButton( this.captionHelpButton );
		await this.waitForDisplayedAndClickable( this.messageDialogText );
		expect( this.messageDialogText ).toContain( 'Write a caption to go with the image in the article' );
	}

	async closeCaptionHelp() {
		await this.clickButton( this.messageDialogActionCloseButton );
	}

	acceptSuggestion() {
		return this.clickButton( this.yesButton );
	}

	clickPublishChangesButton() {
		return this.clickButton( this.publishButton );
	}

	async fillCaption( captionText ) {
		await this.waitForDisplayedAndClickable( this.captionNode );
		await this.captionNode.click();
		await browser.waitUntil( async () => {
			return this.captionNodePlaceholder[ 0 ].classList[ 1 ] === 'oo-ui-element-hidden';
		} );
		await this.captionNode.setValue( captionText );
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

	async setupSuggestions( articleTitle ) {
		if ( this.setupComplete ) {
			return;
		}
		// FIXME: This should run in Quibble. Adding as a workaround, otherwise
		// edit.php results in Wikimedia\Rdbms\DBQueryError:
		// Error 1690: BIGINT UNSIGNED value is out of range in
		// '`wikidb`.`site_stats`.`ss_good_articles` - 1'
		await childProcess.spawnSync(
			'php',
			[ 'maintenance/initSiteStats.php', '--update' ],
			{ cwd: ip }
		);

		await childProcess.spawnSync(
			'php',
			[ 'maintenance/edit.php', '--user=Admin', articleTitle ],
			{ input: 'Blank page.', cwd: ip }
		);
		await childProcess.spawnSync(
			'php',
			[ 'maintenance/edit.php', '--user=Admin', articleTitle + '/addimage.json' ],
			{ input: fs.readFileSync( path.resolve( __dirname + '/../fixtures/' + articleTitle + '.suggestions.json' ) ), cwd: ip }
		);
		this.setupComplete = true;
	}

}

module.exports = AddImageArticlePage;
