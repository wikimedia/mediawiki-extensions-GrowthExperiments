/**
 * Wrapper around VE's back tool
 *
 * @class mw.libs.ge.MachineSuggestionsBack
 * @extends ve.ui.MWBackTool
 *
 * @constructor
 */
function MachineSuggestionsBack() {
	MachineSuggestionsBack.super.apply( this, arguments );
	this.isInternalRouting = false;
}

OO.inheritClass( MachineSuggestionsBack, ve.ui.MWBackTool );

/**
 * Set a flag that indicates whether the back tool is used to navigate between different parts of
 * the flow or not & update the button icon accordingly
 *
 * @param {boolean} isInternalRouting
 */
MachineSuggestionsBack.prototype.toggleInternalRouting = function ( isInternalRouting ) {
	this.isInternalRouting = isInternalRouting;
	if ( isInternalRouting ) {
		this.setIcon( 'arrowPrevious' );
	} else {
		this.setIcon( 'close' );
	}
};

/** @inheritDoc **/
MachineSuggestionsBack.prototype.onSelect = function () {
	if ( this.isInternalRouting ) {
		require( 'mediawiki.router' ).back();
	} else {
		MachineSuggestionsBack.super.prototype.onSelect.apply( this, arguments );
	}
};

module.exports = MachineSuggestionsBack;
