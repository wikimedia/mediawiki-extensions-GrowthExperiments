var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	AddLinkSaveDialogMixin = StructuredTask.AddLinkSaveDialogMixin,
	StructuredTaskSaveDialogMixin = StructuredTask.StructuredTaskSaveDialogMixin;

/**
 * Replacement for the normal VE Save / Publish dialog that replaces the freetext
 * edit summary with a structured summary, and records structured data about the edit.
 *
 * @class mw.libs.ge.ui.AddLinkSaveDialog
 * @extends ve.ui.MWSaveDialog
 * @mixes mw.libs.ge.ui.StructuredTaskSaveDialogMixin
 * @mixes mw.libs.ge.ui.AddLinkSaveDialogMixin
 *
 * @param {Object} [config] Config options
 * @constructor
 */
function AddLinkSaveDialog( config ) {
	AddLinkSaveDialog.super.call( this, config );
	StructuredTaskSaveDialogMixin.call( this, config );
	AddLinkSaveDialogMixin.call( this, config );
}

OO.inheritClass( AddLinkSaveDialog, ve.ui.MWSaveDialog );
OO.mixinClass( AddLinkSaveDialog, StructuredTaskSaveDialogMixin );
OO.mixinClass( AddLinkSaveDialog, AddLinkSaveDialogMixin );

module.exports = AddLinkSaveDialog;
