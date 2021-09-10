var MinimizedToolbarDialogButton = require( './MinimizedToolbarDialogButton.js' );

/**
 * @class mw.libs.ge.StructuredTaskToolbarDialog
 * @extends ve.ui.ToolbarDialog
 * @constructor
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
	 * @property {number} scrollOffset Amount of space between the window and the annotation when scrolled
	 */
	this.scrollOffset = 100;
	/**
	 * @property {number} scrollTimeout Maximum time to spend in ms when scrolling to annotation
	 */
	this.scrollTimeout = 800;
	/**
	 * @property {number} minHeight Minimum value to use for window height (used in setting surface padding value)
	 */
	this.minHeight = 250;
	/**
	 * @property {boolean} isHidden Whether the dialog is out of view
	 */
	this.isHidden = false;
	/**
	 * @property {mw.libs.ge.MinimizedToolbarDialogButton|null} toolbarDialogButton
	 */
	this.toolbarDialogButton = null;
	/**
	 * @property {boolean} isAdvancing Whether there is animation in progress
	 */
	this.isAnimating = false;
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
	this.$element.addClass( 'animate-below' );
	this.isHidden = true;
	this.isFirstRender = true;
	this.toolbarDialogButton.emit( 'dialogVisibilityChanged', false );
};

/**
 * Show the dialog and and hide the re-open dialog button
 **/
StructuredTaskToolbarDialog.prototype.showDialog = function () {
	if ( !this.isHidden ) {
		return;
	}
	this.isHidden = false;
	this.$element.removeClass( 'animate-below' );
	this.toolbarDialogButton.emit( 'dialogVisibilityChanged', true );
};

/**
 * Show dialog upon click on toolbar dialog button
 */
StructuredTaskToolbarDialog.prototype.onToolbarDialogButtonClicked = function () {
	this.showDialog();
};

/**
 * Attach button for re-opening the dialog
 */
StructuredTaskToolbarDialog.prototype.setUpToolbarDialogButton = function () {
	this.toolbarDialogButton = new MinimizedToolbarDialogButton( {
		label: mw.message(
			'growthexperiments-addlink-context-button-show-suggestion'
		).text()
	} );
	this.toolbarDialogButton.on( 'click', this.onToolbarDialogButtonClicked.bind( this ) );
	this.surface.getGlobalOverlay().$element.append( this.toolbarDialogButton.$element );
};

module.exports = StructuredTaskToolbarDialog;
