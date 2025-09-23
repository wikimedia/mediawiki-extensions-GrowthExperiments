const MachineSuggestionsMode = require( './MachineSuggestionsMode.js' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by StructuredTaskDesktopArticleTarget and
 * StructuredTaskMobileArticleTarget.
 *
 * @mixin mw.libs.ge.StructuredTaskArticleTarget
 * @extends ve.init.mw.ArticleTarget
 */
function StructuredTaskArticleTarget() {
	/**
	 * Will be true when the user has switched to regular VE mode from suggestions mode.
	 * This is used so that the behavior upon navigating away can be overridden if needed.
	 *
	 * @type {boolean}
	 */
	this.hasSwitched = false;
	/**
	 * Will be true when the recommendations were submitted but no real edit happened.
	 * For add link, no recommended link was accepted, or, less plausibly, the save conflicted with
	 * (and got auto-merge with) another edit which added the same link.
	 *
	 * @type {boolean}
	 */
	this.madeNullEdit = false;
	this.$element.addClass( 'mw-ge-structuredTaskArticleTarget' );
}

OO.initClass( StructuredTaskArticleTarget );

/**
 * Set machineSuggestions mode (as opposed to 'visual' or 'source')
 *
 * @inheritDoc
 */
StructuredTaskArticleTarget.prototype.getSurfaceConfig = function ( config ) {
	config = config || {};
	config.mode = 'machineSuggestions';
	// Task-specific ArticleTarget -> StructuredTask ArticleTarget -> VE ArticleTarget
	return this.constructor.super.super.prototype.getSurfaceConfig.call( this, config );
};

/**
 * Modify the return value from this.getSaveOptions().
 *
 * @param {Object} defaultSaveOptions
 * @return {Object}
 */
StructuredTaskArticleTarget.prototype.formatSaveOptions = function ( defaultSaveOptions ) {
	// Subclasses can override this method modify the save options.
	return defaultSaveOptions;
};

/**
 * @inheritDoc
 */
StructuredTaskArticleTarget.prototype.getSaveOptions = function () {
	const saveOptions = this.constructor.super.super.prototype.getSaveOptions.call( this );
	// Don't add the article to the user's watchlist if no edits were made
	if ( !this.hasEdits() ) {
		saveOptions.watchlist = 'nochange';
	}
	return this.formatSaveOptions( saveOptions );
};

/** @override */
StructuredTaskArticleTarget.prototype.updateToolbarSaveButtonState = function () {
	// T281452 no-op, we have our own custom logic for this in StructuredTaskSaveDialog
};

/**
 * Actions that should occur before surfaceReady
 *
 * @abstract
 */
StructuredTaskArticleTarget.prototype.beforeStructuredTaskSurfaceReady = null;

/**
 * Actions that should occur after surfaceReady
 *
 * @abstract
 */
StructuredTaskArticleTarget.prototype.afterStructuredTaskSurfaceReady = null;

/** @inheritDoc */
StructuredTaskArticleTarget.prototype.surfaceReady = function () {
	// Put the surface in read-only mode
	this.getSurface().setReadOnly( true );
	// Remove any edit notices (T281960)
	this.editNotices = [];

	this.beforeStructuredTaskSurfaceReady();
	this.constructor.super.super.prototype.surfaceReady.apply( this, arguments );
	this.updateHistory();
	this.afterStructuredTaskSurfaceReady();
	suggestedEditSession.trackEditorReady();

	// Save can be triggered from ToolbarDialog.
	MachineSuggestionsMode.addSaveHook( this.surface );
};

/**
 * Check whether the user has made changes to the article.
 *
 * @abstract
 * @return {boolean}
 */
StructuredTaskArticleTarget.prototype.hasEdits = null;

/**
 * Check whether the user has reviewed any suggestions.
 *
 * @abstract
 * @return {boolean}
 */
StructuredTaskArticleTarget.prototype.hasReviewedSuggestions = null;

/** @inheritDoc */
StructuredTaskArticleTarget.prototype.isSaveable = function () {
	// Call parent method just in case it has some side effect, but ignore its return value.
	this.constructor.super.super.prototype.isSaveable.call( this );
	// The page is saveable if the user accepted or rejected recommendations.
	// (If there are only rejections, the save will be a null edit but it's still a convenient
	// way of handling various needed updates via the same mechanism, so we don't special-case it.)
	return this.hasEdits() || this.hasReviewedSuggestions();
};

/**
 * Don't save or restore edits
 *
 * @override
 */
StructuredTaskArticleTarget.prototype.initAutosave = function () {
	// https://phabricator.wikimedia.org/T267690
};

/**
 * Show a dialog prompting the user to confirm whether to switch to visual editor without
 * saving changes within suggestions mode
 *
 * @param {string} editMode Editing mode to switch to (currently only 'visual' is supported)
 * @return {jQuery.Promise} Promise that resolves when the user has confirmed or cancelled
 */
StructuredTaskArticleTarget.prototype.confirmSwitchEditMode = function ( editMode ) {
	const deferred = $.Deferred(),
		confirmationDialogPromise = this.surface.dialogs.openWindow( 'editModeConfirmation' );

	confirmationDialogPromise.opening.then( () => {
		this.logger.log(
			'impression',
			'',
			// eslint-disable-next-line camelcase
			{ active_interface: 'editmode_confirmation_dialog' },
		);
	} );

	confirmationDialogPromise.closed.then( ( data ) => {
		const isConfirm = data && data.isConfirm;
		this.logger.log(
			isConfirm ? 'editmode_confirm_switch' : 'editmode_cancel_switch',
			/* eslint-disable camelcase */
			isConfirm ? { selected_mode: editMode } : {},
			{ active_interface: 'editmode_confirmation_dialog' },
			/* eslint-enable camelcase */
		);
		return deferred.resolve( isConfirm );
	} );

	return deferred.promise();
};

/**
 * Show a confirmation dialog then switch only if the user confirms leaving with unsaved changes.
 */
StructuredTaskArticleTarget.prototype.maybeSwitchToVisualWithSuggestions = function () {
	this.confirmSwitchEditMode( 'visual' ).then( ( shouldSwitch ) => {
		if ( shouldSwitch ) {
			this.switchToVisualWithSuggestions();
		}
	} );
};

/**
 * Switch to regular Visual Editor mode with customized editMode tool that can only switch
 * between machine suggestions and regular VE modes
 */
StructuredTaskArticleTarget.prototype.switchToVisualWithSuggestions = function () {
	// Prevent default browser warning from being shown since we're showing a custom dialog.
	// On desktop, this is done via DesktopArticleTarget's onBeforeUnload.
	if ( OO.ui.isMobile() ) {
		$( window ).off( 'beforeunload' );
	}

	const url = new URL( window.location.href );
	this.hasSwitched = true;
	// Only include veaction so VE is loaded regardless of the default editor preference.
	url.searchParams.delete( 'action' );
	url.searchParams.set( 'veaction', 'edit' );
	url.searchParams.set( 'hideMachineSuggestions', '1' );
	location.href = url.toString();
};

/**
 * Subclass may implement this if page history needs to be updated after the surface is ready.
 */
StructuredTaskArticleTarget.prototype.updateHistory = function () {
	// Intentionally no-op
};

/**
 * Get MachineSuggestionsPlaceholder tool
 *
 * @return {mw.libs.ge.MachineSuggestionsPlaceholder|undefined}
 */
StructuredTaskArticleTarget.prototype.getPlaceholderTool = function () {
	return this.getToolbar().tools.machineSuggestionsPlaceholder;
};

/**
 * Update the title text in the MachineSuggestionsPlaceholder tool
 *
 * @param {string} title Title text to use
 * @param {boolean} [isLoading] Whether the toolbar is in a loading state
 */
StructuredTaskArticleTarget.prototype.updatePlaceholderTitle = function ( title, isLoading ) {
	const placeholderTool = this.getPlaceholderTool();
	if ( placeholderTool ) {
		placeholderTool.updateTitleText( title, isLoading );
	}
};

/**
 * Restore the original title text in the MachineSuggestionsPlaceholder tool
 */
StructuredTaskArticleTarget.prototype.restorePlaceholderTitle = function () {
	const placeholderTool = this.getPlaceholderTool();
	if ( placeholderTool ) {
		placeholderTool.restoreOriginalTitleText();
	}
};

/**
 * Toggle internal routing mode for the back tool.
 * This is used when the back button is used to navigation between different steps in
 * the editing flow.
 *
 * @param {boolean} isInternalRoutingEnabled
 */
StructuredTaskArticleTarget.prototype.toggleInternalRouting = function ( isInternalRoutingEnabled ) {
	const backTool = this.getToolbar().tools.back;
	if ( backTool && typeof backTool.toggleInternalRouting === 'function' ) {
		backTool.toggleInternalRouting( isInternalRoutingEnabled );
	}
};

/**
 * Show custom error when the user is logged out during editing
 *
 * @override
 */
StructuredTaskArticleTarget.prototype.saveErrorNewUser = function ( username ) {
	const saveDialog = this.surface.getDialogs().currentWindow;
	saveDialog.showUserError( username );
	// HACK: Resolve in order to stop the ProcessDialog's loading state instead of rejecting
	// with an error in order to show a custom message instead
	this.saveDeferred.resolve();
};

/**
 * Show a dialog prompting the user to confirm whether to leave suggestions mode
 *
 * @return {jQuery.Promise} Promise that resolves when the user has confirmed or cancelled
 */
StructuredTaskArticleTarget.prototype.confirmLeavingSuggestionsMode = function () {
	const deferred = $.Deferred();
	const abandonEditDialogPromise = this.getSurface().dialogs.openWindow( 'abandonedit' ),
		// eslint-disable-next-line camelcase
		metadataOverride = { active_interface: 'abandonedit_dialog' };

	abandonEditDialogPromise.opening.then( () => {
		this.logger.log( 'impression', '', metadataOverride );
	} );

	abandonEditDialogPromise.closed.then( ( data ) => {
		if ( data && data.action === 'discard' ) {
			this.logger.log( 'discard', '', metadataOverride );
			return deferred.resolve().promise();
		}
		this.logger.log( 'keep', '', metadataOverride );
		return deferred.reject();
	} );
	return deferred.promise();
};

/**
 * Exit the editing mode without showing any prompts
 *
 * @abstract
 * @param {string} [trackMechanism] Abort mechanism (passed from tryTeardown)
 * @return {jQuery.Promise} Promise that resolves when the surface has been torn down
 */
StructuredTaskArticleTarget.prototype.teardownWithoutPrompt = null;

/** @inheritDoc **/
StructuredTaskArticleTarget.prototype.tryTeardown = function ( noPrompt, trackMechanism ) {
	if ( this.edited || this.hasReviewedSuggestions() ) {
		return this.constructor.super.super.prototype.tryTeardown.call(
			this, noPrompt, trackMechanism,
		);
	}
	// Show a confirmation when the user hasn't made any edits (T300582)
	return this.confirmLeavingSuggestionsMode().then( () => this.teardownWithoutPrompt( trackMechanism ) );
};

/**
 * Reload the page when saving structured task edits since structured task edits can't be made again
 * The enhancement to update the page dynamically is tracked via T308046.
 *
 * @override
 */
StructuredTaskArticleTarget.prototype.saveComplete = function ( data ) {
	this.emit( 'save', data );
	this.hasSaved = true;
	suggestedEditSession.onStructuredTaskSaved();

	const url = new URL( window.location.href );
	url.searchParams.delete( 'gesuggestededit' );
	url.searchParams.delete( 'veaction' );

	// Explicitly clear the hash/fragment to prevent VE re-initialization
	// VE uses fragments like #/editor/all to maintain editor state
	// We must clear this when exiting the editor to ensure a clean return to reading mode
	url.hash = '';

	if ( this.saveDialog && this.saveDialog.isOpened() ) {
		this.saveDialog.close();
	}
	// Skip default warnings when leaving the page
	$( window ).off( 'beforeunload' );
	window.onbeforeunload = null;
	window.location.href = url.toString();
};

module.exports = StructuredTaskArticleTarget;
