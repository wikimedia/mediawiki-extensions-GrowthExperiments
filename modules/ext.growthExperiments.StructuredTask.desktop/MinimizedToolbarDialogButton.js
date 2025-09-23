/**
 * Button for re-opening the inspector
 *
 * @class mw.libs.ge.MinimizedToolbarDialogButton
 * @extends OO.ui.ButtonWidget
 * @param {Object} [config]
 * @param {string} [config.label] Invisible label for the button
 * @constructor
 */
function MinimizedToolbarDialogButton( config ) {
	MinimizedToolbarDialogButton.super.call( this, config );
	if ( config && config.label ) {
		this.setLabel( config.label );
		this.setInvisibleLabel( true );
	}
	this.$element.addClass( [
		'mw-ge-minimizedToolbarDialogButton',
		'mw-ge-minimizedToolbarDialogButton--hidden',
	] );
	this.on( 'dialogVisibilityChanged', this.onDialogVisibilityChanged.bind( this ) );
}

OO.inheritClass( MinimizedToolbarDialogButton, OO.ui.ButtonWidget );

MinimizedToolbarDialogButton.static.icon = 'robot';
MinimizedToolbarDialogButton.static.flags = [ 'primary', 'progressive' ];

/**
 * Hide the button if the toolbar dialog is shown,
 * show the button if the toolbar dialog is hidden
 *
 * @param {boolean} isDialogVisible Whether the dialog is visible
 */
MinimizedToolbarDialogButton.prototype.onDialogVisibilityChanged = function ( isDialogVisible ) {
	this.$element.toggleClass( 'mw-ge-minimizedToolbarDialogButton--hidden', isDialogVisible );
};

module.exports = MinimizedToolbarDialogButton;
