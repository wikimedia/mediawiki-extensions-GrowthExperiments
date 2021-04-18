var AddLinkSaveDialogMixin = require( 'ext.growthExperiments.AddLink' ).AddLinkSaveDialogMixin;

/**
 * Replacement for the normal mobile VE Save / Publish dialog that replaces the freetext
 * edit summary with a structured summary, and records structured data about the edit.
 *
 * @class mw.libs.ge.ui.AddLinkMobileSaveDialog
 * @extends ve.ui.MWMobileSaveDialog
 * @mixes mw.libs.ge.ui.AddLinkSaveDialogMixin
 *
 * @param {Object} [config] Config options
 * @constructor
 */
function AddLinkMobileSaveDialog( config ) {
	AddLinkMobileSaveDialog.super.call( this, config );
	AddLinkSaveDialogMixin.call( this, config );
}
OO.inheritClass( AddLinkMobileSaveDialog, ve.ui.MWMobileSaveDialog );
OO.mixinClass( AddLinkMobileSaveDialog, AddLinkSaveDialogMixin );

module.exports = AddLinkMobileSaveDialog;
