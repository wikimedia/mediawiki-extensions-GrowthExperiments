/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by StructuredTaskDesktopArticleTarget and
 * StructuredTaskMobileArticleTarget.
 *
 * @mixin mw.libs.ge.StructuredTaskArticleTargetMixin
 * @extends ve.init.mw.ArticleTarget
 */
function StructuredTaskArticleTargetMixin() {
	/**
	 * Will be true when the user has switched to regular VE mode from suggestions mode.
	 * This is used so that the behavior upon navigating away can be overridden if needed.
	 *
	 * @type {boolean}
	 */
	this.hasSwitched = false;
	this.$element.addClass( 'mw-ge-structuredTaskArticleTarget' );
}

OO.initClass( StructuredTaskArticleTargetMixin );

/**
 * Show a dialog prompting the user to confirm whether to switch to visual editor without
 * saving changes within suggestions mode
 *
 * @param {string} editMode Editing mode to switch to (currently only 'visual' is supported)
 * @return {jQuery.Promise} Promise that resolves when the user has confirmed or cancelled
 */
StructuredTaskArticleTargetMixin.prototype.confirmSwitchEditMode = function ( editMode ) {
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
StructuredTaskArticleTargetMixin.prototype.maybeSwitchToVisualWithSuggestions = function () {
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
StructuredTaskArticleTargetMixin.prototype.switchToVisualWithSuggestions = function () {
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

module.exports = StructuredTaskArticleTargetMixin;
