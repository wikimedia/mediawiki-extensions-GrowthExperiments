const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	AddLinkSaveDialog = StructuredTask.addLink().AddLinkSaveDialog;

/**
 * Replacement for the normal mobile VE Save / Publish dialog that replaces the freetext
 * edit summary with a structured summary, and records structured data about the edit.
 *
 * @class mw.libs.ge.ui.AddLinkMobileSaveDialog
 * @extends ve.ui.MWMobileSaveDialog
 * @mixes mw.libs.ge.ui.AddLinkSaveDialog
 *
 * @param {Object} [config] Config options
 * @constructor
 */
function AddLinkMobileSaveDialog( config ) {
	AddLinkMobileSaveDialog.super.call( this, config );
	AddLinkSaveDialog.call( this, config );
}

OO.inheritClass( AddLinkMobileSaveDialog, ve.ui.MWMobileSaveDialog );
OO.mixinClass( AddLinkMobileSaveDialog, AddLinkSaveDialog );

module.exports = AddLinkMobileSaveDialog;
