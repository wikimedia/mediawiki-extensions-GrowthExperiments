const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	AddImageSaveDialog = StructuredTask.addImage().AddImageSaveDialog;

/**
 * Replacement for the normal mobile VE Save / Publish dialog that replaces the freetext
 * edit summary with a structured summary, and records structured data about the edit.
 *
 * @class mw.libs.ge.ui.AddImageMobileSaveDialog
 * @extends ve.ui.MWMobileSaveDialog
 * @mixes mw.libs.ge.ui.AddImageSaveDialog
 *
 * @param {Object} [config] Config options
 * @constructor
 */
function AddImageMobileSaveDialog( config ) {
	AddImageMobileSaveDialog.super.call( this, config );
	AddImageSaveDialog.call( this, config );
}

OO.inheritClass( AddImageMobileSaveDialog, ve.ui.MWMobileSaveDialog );
OO.mixinClass( AddImageMobileSaveDialog, AddImageSaveDialog );

module.exports = AddImageMobileSaveDialog;
