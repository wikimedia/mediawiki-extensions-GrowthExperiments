'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Api = require( 'wdio-mediawiki/Api' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../../' );
const Util = require( 'wdio-mediawiki/Util' );

class HomepagePage extends Page {
	get homepage() { return $( '#ca-homepage' ); }
	get firstheading() { return $( '#firstHeading' ); }
	get suggestedEditsCard() { return $( '.suggested-edits-card' ); }
	get suggestedEditsCardTitle() { return $( '.se-card-title' ); }
	get suggestedEditsCardUrl() { return $( '.suggested-edits-card a' ); }
	get suggestedEditsPreviousButton() { return $( '.suggested-edits-previous .oo-ui-buttonElement-button' ); }
	get suggestedEditsNextButton() { return $( '.suggested-edits-next' ); }
	get newcomerTaskArticleEditButton() { return $( '#ca-ve-edit' ); }
	get newcomerTaskArticleSaveButton() { return $( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary' ); }
	get savePageDots() { return $( '.ve-ui-toolbar-saveButton' ); }
	get articleBodyContent() { return $( '.mw-body-content.ve-ui-surface' ); }
	get postEditDialog() { return $( '.mw-ge-postEditDrawer' ); }
	get postEditDialogSmallTaskCard() { return $( '.mw-ge-postEditDrawer .mw-ge-small-task-card' ); }

	open( query, fragment ) {
		query = query || {};
		fragment = fragment || '';
		super.openTitle( 'Special:Homepage', query, fragment );
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

	async editAndSaveArticle( textToAppend ) {
		await Util.waitForModuleState( 'ext.visualEditor.desktopArticleTarget', 'registered' );
		await this.newcomerTaskArticleEditButton.waitForExist();
		await this.newcomerTaskArticleEditButton.waitForClickable();
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

	async rebuildRecentChanges( message ) {
		if ( message ) {
			console.log( message );
		} else {
			console.log( 'Rebuilding recent changes...' );
		}
		// TODO: In CI, this is fast but in a local wiki this can take a long time;
		// we should pass a timestamp so that we just rebuild edits made in the last
		// couple of minutes.
		await childProcess.spawnSync(
			'php',
			[ 'maintenance/rebuildrecentchanges.php' ],
			{ cwd: ip }
		);
	}

	async waitForPostEditDialog() {
		await this.waitForDisplayedAndClickable( this.postEditDialog );
	}

	async waitForDisplayedAndClickable( element ) {
		await element.waitForDisplayed( { timeout: 30000 } );
		await element.waitForClickable( { timeout: 30000 } );
	}
}

module.exports = new HomepagePage();
