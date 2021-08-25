/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogButton
 * @extends OO.ui.ButtonWidget
 * @constructor
 */
function RecommendedLinkToolbarDialogButton() {
	RecommendedLinkToolbarDialogButton.super.apply( this, arguments );
	this.setInvisibleLabel( true );
	this.$element.addClass( [
		'mw-ge-recommendedLinkToolbarDialog-button',
		'animate-below'
	] );
	this.on( 'dialogVisibilityChanged', this.onDialogVisibilityChanged.bind( this ) );
}

OO.inheritClass( RecommendedLinkToolbarDialogButton, OO.ui.ButtonWidget );

RecommendedLinkToolbarDialogButton.static.icon = 'robot';
RecommendedLinkToolbarDialogButton.static.flags = [ 'progressive' ];
RecommendedLinkToolbarDialogButton.static.label = mw.message(
	'growthexperiments-addlink-context-button-show-suggestion'
).text();

/**
 * Hide the button if the toolbar dialog is shown,
 * show the button if the toolbar dialog is hidden
 *
 * @param {boolean} isDialogVisible Whether the dialog is visible
 */
RecommendedLinkToolbarDialogButton.prototype.onDialogVisibilityChanged = function ( isDialogVisible ) {
	this.$element.toggleClass( 'animate-below', isDialogVisible );
};

module.exports = RecommendedLinkToolbarDialogButton;
