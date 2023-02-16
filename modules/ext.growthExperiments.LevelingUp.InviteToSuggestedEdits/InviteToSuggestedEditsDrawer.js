'use strict';

var CollapsibleDrawer = require( '../ui-components/CollapsibleDrawer.js' ),
	InviteToSuggestedEditsPanel = require( './InviteToSuggestedEditsPanel.js' );

/**
 * @class mw.libs.ge.InviteToSuggestedEditsDrawer
 * @extends mw.libs.ge.CollapsibleDrawer
 * @constructor
 */
function InviteToSuggestedEditsDrawer() {
	this.panel = new InviteToSuggestedEditsPanel();
	InviteToSuggestedEditsDrawer.super.call( this, {
		headerText: this.panel.getHeaderText(),
		content: this.panel.getMainArea().concat( this.panel.getFooterButtons() ),
		padded: false
	} );
	this.$element.addClass( [
		'mw-ge-inviteToSuggestedEditsDrawer',
		OO.ui.isMobile() ?
			'mw-ge-inviteToSuggestedEditsDrawer-mobile' :
			'mw-ge-inviteToSuggestedEditsDrawer-desktop'
	] );
	this.panel.connect( this, {
		close: 'onClose'
	} );
}
OO.inheritClass( InviteToSuggestedEditsDrawer, CollapsibleDrawer );

InviteToSuggestedEditsDrawer.prototype.onClose = function () {
	this.close( {} );
};

module.exports = InviteToSuggestedEditsDrawer;
