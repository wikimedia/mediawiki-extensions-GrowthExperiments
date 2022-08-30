'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Api = require( 'wdio-mediawiki/Api' ),
	Util = require( 'wdio-mediawiki/Util' ),
	assert = require( 'assert' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../../' );

class HomepagePage extends Page {
	get homepage() { return $( '#ca-homepage' ); }
	get firstheading() { return $( '#firstHeading' ); }
	get suggestedEditsCard() { return $( '.suggested-edits-card' ); }
	get suggestedEditsCardTitle() { return $( '.se-card-title' ); }
	get suggestedEditsCardUrl() { return $( '.suggested-edits-card a' ); }
	get suggestedEditsPreviousButton() { return $( '.suggested-edits-previous .oo-ui-buttonElement-button' ); }
	get suggestedEditsNextButton() { return $( '.suggested-edits-next .oo-ui-buttonElement-button' ); }
	get newcomerTaskArticleEditButton() { return $( '#ca-ve-edit' ); }
	get newcomerTaskArticleSaveButton() { return $( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary' ); }
	get helpPanelCloseButton() { return $( '.mw-ge-help-panel-processdialog .oo-ui-processDialog-actions-primary .oo-ui-buttonElement-button' ); }
	get savePageDots() { return $( '.ve-ui-toolbar-saveButton' ); }
	get articleBodyContent() { return $( '.mw-body-content.ve-ui-surface' ); }
	get postEditDialog() { return $( '.mw-ge-postEditDrawer' ); }
	get postEditDialogSmallTaskCard() { return $( '.mw-ge-postEditDrawer .mw-ge-small-task-card' ); }

	open( query, fragment ) {
		query = query || {};
		fragment = fragment || '';
		super.openTitle( 'Special:Homepage', query, fragment );
	}

	async assertCardTitleIs( titleText ) {
		await browser.waitUntil( async () => {
			return await this.suggestedEditsCardTitle.getText() === titleText;
		} );
		assert.strictEqual( await this.suggestedEditsCardTitle.getText(), titleText );
	}

	async waitForInteractiveTaskFeed() {
		// The previous/next buttons start out as disabled, and then are switched to
		// enabled/disabled depending on where in the task queue the user is.
		await browser.waitUntil( async () => {
			return await this.suggestedEditsNextButton.getAttribute( 'aria-disabled' ) !== 'true';
		} );
		await this.suggestedEditsNextButton.waitForClickable();
		assert.strictEqual( await this.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'true' );
		assert.notEqual( await this.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'true' );
	}

	async advanceToNextCard() {
		const oldTitle = await this.suggestedEditsCardTitle.getText();
		await browser.waitUntil( async () => {
			return await this.suggestedEditsNextButton.isClickable();
		} );
		await this.suggestedEditsNextButton.click();
		await browser.waitUntil( async () => {
			return await this.suggestedEditsCardTitle.getText() !== oldTitle;
		} );
	}

	async goBackToPreviousCard() {
		const oldTitle = await this.suggestedEditsCardTitle.getText();
		await this.suggestedEditsPreviousButton.click();
		await browser.waitUntil( async () => {
			return await this.suggestedEditsCardTitle.getText() !== oldTitle;
		} );
		await this.suggestedEditsNextButton.isClickable();
	}

	async assertPreviousButtonIsDisabled() {
		assert.strictEqual( await this.suggestedEditsPreviousButton.getAttribute( 'aria-disabled' ), 'true' );
	}

	async assertNextButtonIsDisabled() {
		assert.strictEqual( await this.suggestedEditsNextButton.getAttribute( 'aria-disabled' ), 'true' );
	}

	async waitUntilRecentChangesItemExists( tag, title, user ) {
		let result;
		await browser.waitUntil( async () => {
			const bot = await Api.bot();
			result = await bot.request( {
				action: 'query',
				list: 'recentchanges',
				rctag: tag,
				rctitle: title,
				rcuser: user
			} );
			return result.query.recentchanges.length >= 1;
		} );
		return result;
	}

	/**
	 * @param {string} textToAppend The contents to add to the body of the article being edited.
	 * @param {boolean} closeHelpPanel Whether the help panel should be closed before attempting
	 *   to click Edit.
	 * @return {Promise<void>}
	 */
	async editAndSaveArticle( textToAppend, closeHelpPanel = false ) {
		await Util.waitForModuleState( 'ext.visualEditor.desktopArticleTarget', 'registered' );
		if ( closeHelpPanel ) {
			await this.waitForDisplayedAndClickable( this.helpPanelCloseButton );
			await this.helpPanelCloseButton.click();
		}
		await this.waitForDisplayedAndClickable( this.newcomerTaskArticleEditButton );
		await this.newcomerTaskArticleEditButton.click();
		await Util.waitForModuleState( 'ext.visualEditor.desktopArticleTarget', 'ready' );
		try {
			await this.articleBodyContent.waitForClickable( { timeout: 20000 } );
			await this.articleBodyContent.click();
		} catch ( e ) {
			// There seems to be some race condition where the edit button is clickable, but
			// clicking doesn't load the VE surface. When that happens, reload the page but
			// with veaction=edit so we're in editing mode.
			const url = new URL( await browser.getUrl() );
			url.searchParams.append( 'veaction', 'edit' );
			await browser.url( url.toString() );
			// Set up the interceptor again as we've reloaded the page.
			await browser.setupInterceptor();
			await this.articleBodyContent.waitForClickable( { timeout: 20000 } );
			await this.articleBodyContent.click();
		}
		await browser.keys( textToAppend );
		await this.savePageDots.click();
		await this.newcomerTaskArticleSaveButton.waitForClickable();
		await this.newcomerTaskArticleSaveButton.click();

		await Util.waitForModuleState( 'ext.growthExperiments.PostEdit' );

		await this.waitForPostEditDialog();
	}

	async runJobs( message ) {
		if ( message ) {
			console.log( message );
		} else {
			console.log( 'Running jobs' );
		}
		await childProcess.spawnSync(
			'php',
			[ 'maintenance/runJobs.php' ],
			{ cwd: ip }
		);
	}

	async waitForPostEditDialog() {
		await this.waitForDisplayedAndClickable( this.postEditDialog );
	}

	async waitForDisplayedAndClickable( element ) {
		await element.waitForExist( { timeout: 30000 } );
		await element.waitForDisplayed( { timeout: 30000 } );
		await element.waitForClickable( { timeout: 30000 } );
	}
}

module.exports = new HomepagePage();
