var MachineSuggestionsMode = require( './MachineSuggestionsMode.js' ),
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
	return this.constructor.parent.super.prototype.getSurfaceConfig.call( this, config );
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
	var saveOptions = this.constructor.parent.super.prototype.getSaveOptions.call( this );
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
StructuredTaskArticleTarget.prototype.beforeStructuredTaskSurfaceReady = function () {
	throw new Error( 'beforeStructuredTaskSurfaceReady must be implemented by subclass' );
};

/**
 * Actions that should occur after surfaceReady
 *
 * @abstract
 */
StructuredTaskArticleTarget.prototype.afterStructuredTaskSurfaceReady = function () {
	throw new Error( 'afterStructuredTaskSurfaceReady must be implemented by subclass' );
};

/** @inheritDoc */
StructuredTaskArticleTarget.prototype.surfaceReady = function () {
	// Put the surface in read-only mode
	this.getSurface().setReadOnly( true );
	// Remove any edit notices (T281960)
	this.editNotices = [];

	this.beforeStructuredTaskSurfaceReady();
	this.constructor.parent.super.prototype.surfaceReady.apply( this, arguments );
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
 *
 * @return {boolean}
 */
StructuredTaskArticleTarget.prototype.hasEdits = function () {
	throw new Error( 'hasEdits must be implemented by subclass' );
};

/**
 * Check whether the user has reviewed any suggestions.
 *
 * @abstract
 *
 * @return {boolean}
 */
StructuredTaskArticleTarget.prototype.hasReviewedSuggestions = function () {
	throw new Error( 'hasReviewedSuggestions must be implemented by subclass' );
};

/** @inheritDoc */
StructuredTaskArticleTarget.prototype.isSaveable = function () {
	// Call parent method just in case it has some side effect, but ignore its return value.
	this.constructor.parent.super.prototype.isSaveable.call( this );
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
	var promise = $.Deferred(),
		confirmationDialogPromise = this.surface.dialogs.openWindow( 'editModeConfirmation' );

	confirmationDialogPromise.opening.then( function () {
		this.logger.log(
			'impression',
			'',
			// eslint-disable-next-line camelcase
			{ active_interface: 'editmode_confirmation_dialog' }
		);
	}.bind( this ) );

	confirmationDialogPromise.closed.then( function ( data ) {
		var isConfirm = data && data.isConfirm;
		this.logger.log(
			isConfirm ? 'editmode_confirm_switch' : 'editmode_cancel_switch',
			/* eslint-disable camelcase */
			isConfirm ? { selected_mode: editMode } : {},
			{ active_interface: 'editmode_confirmation_dialog' }
			/* eslint-enable camelcase */
		);
		return promise.resolve( isConfirm );
	}.bind( this ) );

	return promise;
};

/**
 * Show a confirmation dialog then switch only if the user confirms leaving with unsaved changes.
 */
StructuredTaskArticleTarget.prototype.maybeSwitchToVisualWithSuggestions = function () {
	this.confirmSwitchEditMode( 'visual' ).then( function ( shouldSwitch ) {
		if ( shouldSwitch ) {
			this.switchToVisualWithSuggestions();
		}
	}.bind( this ) );
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

	var uri = new mw.Uri(),
		fragment = uri.fragment;
	this.hasSwitched = true;
	// Only include veaction so VE is loaded regardless of the default editor preference.
	delete uri.query.action;
	uri.query.veaction = 'edit';
	uri.query.hideMachineSuggestions = 1;
	// uri.toString encodes fragment by default, breaking fragments such as "/editor/all".
	uri.fragment = '';
	location.href = uri.toString() + ( fragment ? '#' + fragment : '' );
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
	var placeholderTool = this.getPlaceholderTool();
	if ( placeholderTool ) {
		placeholderTool.updateTitleText( title, isLoading );
	}
};

/**
 * Restore the original title text in the MachineSuggestionsPlaceholder tool
 */
StructuredTaskArticleTarget.prototype.restorePlaceholderTitle = function () {
	var placeholderTool = this.getPlaceholderTool();
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
	var backTool = this.getToolbar().tools.back;
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
	var saveDialog = this.surface.getDialogs().currentWindow;
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
	var promise = $.Deferred();
	var abandonEditDialogPromise = this.getSurface().dialogs.openWindow( 'abandonedit' ),
		// eslint-disable-next-line camelcase
		metadataOverride = { active_interface: 'abandonedit_dialog' };

	abandonEditDialogPromise.opening.then( function () {
		this.logger.log( 'impression', '', metadataOverride );
	}.bind( this ) );

	abandonEditDialogPromise.closed.then( function ( data ) {
		if ( data && data.action === 'discard' ) {
			this.logger.log( 'discard', '', metadataOverride );
			return promise.resolve();
		}
		this.logger.log( 'keep', '', metadataOverride );
		return promise.reject();
	}.bind( this ) );
	return promise;
};

/**
 * Exit the editing mode without showing any prompts
 * @abstract
 *
 * @param {string} [trackMechanism] Abort mechanism (passed from tryTeardown)
 * @return {jQuery.Promise} Promise that resolves when the surface has been torn down
 */
StructuredTaskArticleTarget.prototype.teardownWithoutPrompt = function ( trackMechanism ) {
	throw new Error(
		'teardownWithoutPrompt must be implemented by subclass. trackMechanism: ' + trackMechanism
	);
};

/** @inheritDoc **/
StructuredTaskArticleTarget.prototype.tryTeardown = function ( noPrompt, trackMechanism ) {
	if ( this.edited || this.hasReviewedSuggestions() ) {
		return this.constructor.parent.super.prototype.tryTeardown.call(
			this, noPrompt, trackMechanism
		);
	}
	// Show a confirmation when the user hasn't made any edits (T300582)
	return this.confirmLeavingSuggestionsMode().then( function () {
		return this.teardownWithoutPrompt( trackMechanism );
	}.bind( this ) );
};

module.exports = StructuredTaskArticleTarget;
