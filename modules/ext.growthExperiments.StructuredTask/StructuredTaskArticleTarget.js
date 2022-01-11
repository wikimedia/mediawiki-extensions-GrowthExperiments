var MachineSuggestionsMode = require( './MachineSuggestionsMode.js' );

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
 * If the user has started reviewing suggestions, show a confirmation dialog
 * then switch only if the user confirms leaving with unsaved changes.
 * If the user hasn't started, switch to regular VE right away.
 */
StructuredTaskArticleTarget.prototype.maybeSwitchToVisualWithSuggestions = function () {
	if ( this.edited ) {
		this.confirmSwitchEditMode( 'visual' ).then( function ( shouldSwitch ) {
			if ( shouldSwitch ) {
				this.switchToVisualWithSuggestions();
			}
		}.bind( this ) );
	} else {
		this.switchToVisualWithSuggestions();
	}
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

module.exports = StructuredTaskArticleTarget;
