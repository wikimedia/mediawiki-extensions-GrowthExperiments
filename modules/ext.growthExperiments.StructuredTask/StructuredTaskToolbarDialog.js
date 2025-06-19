const HelpPanelButton = require( '../ui-components/HelpPanelButton.js' );

/**
 * @class mw.libs.ge.StructuredTaskToolbarDialog
 * @extends ve.ui.ToolbarDialog
 * @constructor
 * @property {mw.libs.ge.StructuredTaskLogger} logger
 */
function StructuredTaskToolbarDialog() {
	StructuredTaskToolbarDialog.super.apply( this, arguments );
	this.$element.addClass( [
		'mw-ge-structuredTaskToolbarDialog',
		OO.ui.isMobile() ?
			'mw-ge-structuredTaskToolbarDialog-mobile' :
			'mw-ge-structuredTaskToolbarDialog-desktop'
	] );
	/**
	 * @property {ve.ui.Surface} surface VisualEditor UI surface
	 */
	this.surface = null;
	/**
	 * @property {number} scrollOffset Amount of space between the window and the annotation
	 *   when scrolling
	 */
	this.scrollOffset = 100;
	/**
	 * @property {number} scrollTimeout Maximum time to spend in ms when scrolling to annotation
	 */
	this.scrollTimeout = 800;
	/**
	 * @property {number} minHeight Minimum value to use for window height (used in setting
	 *   surface padding value)
	 */
	this.minHeight = 250;
	/**
	 * @property {boolean} isHidden Whether the dialog is out of view
	 */
	this.isHidden = false;
	/**
	 * @property {boolean} isAdvancing Whether there is animation in progress
	 */
	this.isAnimating = false;
	/**
	 * @property {number} currentIndex Zero-based index of the selected recommendation
	 */
	this.currentIndex = 0;
	/**
	 * @property {OO.ui.ButtonWidget|null} helpButton Button for opening the help panel
	 */
	this.helpButton = null;
}

OO.inheritClass( StructuredTaskToolbarDialog, ve.ui.ToolbarDialog );

/**
 * Get robot icon element
 *
 * @return {jQuery}
 */
StructuredTaskToolbarDialog.prototype.getRobotIcon = function () {
	return new OO.ui.IconWidget( {
		icon: 'robot',
		label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-machine-description' ),
		invisibleLabel: true,
		classes: [ 'mw-ge-structuredTaskToolbarDialog-head-robot-icon' ]
	} ).$element;
};

/**
 * @inheritdoc
 */
StructuredTaskToolbarDialog.prototype.onDialogKeyDown = function ( e ) {
	if ( e.which === OO.ui.Keys.ESCAPE ) {
		// We want to behave as if the dialog were part of the editing surface, ie. on Esc
		// close the editor instead of the dialog.
		e.preventDefault();
		e.stopPropagation();
		ve.init.target.tryTeardown( false, 'navigate-read' );
	} else {
		return StructuredTaskToolbarDialog.super.prototype.onDialogKeyDown.call( this, e );
	}
};

/**
 * Hide the dialog if it's not already hidden and if animation is not in progress
 * and show the re-open dialog button
 */
StructuredTaskToolbarDialog.prototype.hideDialog = function () {
	if ( this.isHidden || this.isAnimating ) {
		return;
	}
	this.$element.addClass( 'collapsed' );
	this.isHidden = true;
	this.chevronIcon.setIcon( 'collapse' );
	this.logger.log( 'collapse', this.getSuggestionLogActionData() );
};

/**
 * Show the dialog and hide the re-open dialog button
 */
StructuredTaskToolbarDialog.prototype.showDialog = function () {
	if ( !this.isHidden ) {
		return;
	}
	this.isHidden = false;
	this.$element.removeClass( 'collapsed' );
	this.chevronIcon.setIcon( 'expand' );
	this.logger.log( 'expand', this.getSuggestionLogActionData() );
};

/**
 * Attach button for collapsing and expanding the dialog
 *
 * @param {string} [label] Text to use for the button's invisible label
 */
StructuredTaskToolbarDialog.prototype.setUpToolbarDialogButton = function ( label ) {
	const $header = this.$head;
	this.chevronIcon = new OO.ui.IconWidget( {
		classes: [ 'mw-ge-structuredTaskToolbarDialog-chevron' ],
		framed: false,
		icon: 'expand',
		label: label,
		invisibleLabel: true
	} );
	$header.on( 'click', this.toggleDisplayState.bind( this ) );
	$header.addClass( 'mw-ge-structuredTaskToolbarDialog-headerButton' )
		.append( this.chevronIcon.$element );
	if ( !OO.ui.isMobile() ) {
		$( document.body ).find( '#footer-places' ).addClass(
			'footer-places--with-mw-ge-structuredTaskToolbarDialog'
		);
	}
};

/**
 * Expand the dialog if it's hidden, collapse the dialog if it's shown
 */
StructuredTaskToolbarDialog.prototype.toggleDisplayState = function () {
	if ( this.isHidden ) {
		this.showDialog();
	} else {
		this.hideDialog();
	}
};

/**
 * Set up button that opens help panel
 *
 * @param {string} [label] Text to use for the button's invisible label
 */
StructuredTaskToolbarDialog.prototype.setupHelpButton = function ( label ) {
	this.helpButton = new HelpPanelButton( {
		label: label
	} );
	this.helpButton.on( 'click', () => {
		mw.hook( 'growthExperiments.contextItem.openHelpPanel' ).fire( this.helpButton );
	} );
	this.$element.append( this.helpButton.$element );
};

/**
 * Return focus to the dialog, so that navigation with tab, Esc etc. works.
 */
StructuredTaskToolbarDialog.prototype.regainFocus = function () {
	this.$content.get( 0 ).focus( { preventScroll: true } );
};

/**
 * Add the article title to the document.
 * This can be used when the editing mode doesn't show the title by default.
 */
StructuredTaskToolbarDialog.prototype.addArticleTitle = function () {
	const SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' );
	this.surface.getView().$documentNode.prepend( $( '<h1>' ).text(
		SuggestedEditSession.getInstance().getCurrentTitle().getNameText()
	) );
};

/**
 * Get the suggestion-specific action data to pass to StructuredTaskLogger.
 *
 * @return {Object}
 */
StructuredTaskToolbarDialog.prototype.getSuggestionLogActionData = function () {
	return {};
};

/**
 * Navigate to suggested edits feed
 */
StructuredTaskToolbarDialog.prototype.goToSuggestedEdits = function () {
	// Allow redirecting user if they click "Unsure", the document
	// has unsaved changes but we don't care about those in this context.
	$( window ).off( 'beforeunload' );
	window.onbeforeunload = null;
	const Utils = require( '../utils/Utils.js' );
	window.location.href = Utils.getSuggestedEditsFeedUrl( 'suggestion-skip' );
};

/**
 * Move the ToolbarDialog from the toolbar to the surface view.
 *
 * This is used on desktop to position the dialog within the article instead of the toolbar.
 */
StructuredTaskToolbarDialog.prototype.moveDialogToSurfaceView = function () {
	this.$element.detach();
	this.surface.getView().$element.append( this.$element );
};

/**
 * Position the VE surface so that the place where the toolbar dialog is supposed to be is
 * visible and convenient to interact with.
 *
 * @return {jQuery.Promise}
 */
StructuredTaskToolbarDialog.prototype.scrollToToolbarDialogTarget = function () {
	// Child classes should override this.
	// FIXME we could probably just scroll to the dialog's location instead if we unhid it
	//   before scrolling.
	return $.Deferred().resolve();
};

module.exports = StructuredTaskToolbarDialog;
