'use strict';

const CollapsibleDrawer = require( '../ui-components/CollapsibleDrawer.js' ),
	InviteToSuggestedEditsPanel = require( './InviteToSuggestedEditsPanel.js' );

/**
 * @class mw.libs.ge.InviteToSuggestedEditsDrawer
 * @extends mw.libs.ge.CollapsibleDrawer
 * @constructor
 * @param {mw.libs.ge.HelpPanelLogger} helpPanelLogger
 */
function InviteToSuggestedEditsDrawer( helpPanelLogger ) {
	this.panel = new InviteToSuggestedEditsPanel( helpPanelLogger );
	this.helpPanelLogger = helpPanelLogger;
	InviteToSuggestedEditsDrawer.super.call( this, {
		headerText: this.panel.getHeaderText(),
		content: this.panel.getMainArea().concat( this.panel.getFooterButtons() ),
		padded: false,
	} );
	this.$element.addClass( [
		'mw-ge-inviteToSuggestedEditsDrawer',
		OO.ui.isMobile() ?
			'mw-ge-inviteToSuggestedEditsDrawer-mobile' :
			'mw-ge-inviteToSuggestedEditsDrawer-desktop',
	] );
	this.panel.connect( this, {
		close: 'onClose',
	} );
}
OO.inheritClass( InviteToSuggestedEditsDrawer, CollapsibleDrawer );

InviteToSuggestedEditsDrawer.prototype.onClose = function () {
	this.close( {} );
};

/**
 * Log that the panel was shown.
 * This should be called when appropriate by the code that instantiates the panel.
 */
InviteToSuggestedEditsDrawer.prototype.logImpression = function () {
	this.helpPanelLogger.log( 'postedit-impression' );
};

/**
 * Log that the panel was closed.
 * This should be called when appropriate by the code that instantiates the panel.
 */
InviteToSuggestedEditsDrawer.prototype.logClose = function () {
	this.helpPanelLogger.log( 'postedit-close' );
};

/** @inheritDoc */
InviteToSuggestedEditsDrawer.prototype.expand = function () {
	this.helpPanelLogger.log( 'postedit-expand' );
	InviteToSuggestedEditsDrawer.super.prototype.expand.call( this );
};

/** @inheritDoc */
InviteToSuggestedEditsDrawer.prototype.collapse = function () {
	this.helpPanelLogger.log( 'postedit-collapse' );
	InviteToSuggestedEditsDrawer.super.prototype.collapse.call( this );
};

module.exports = InviteToSuggestedEditsDrawer;
