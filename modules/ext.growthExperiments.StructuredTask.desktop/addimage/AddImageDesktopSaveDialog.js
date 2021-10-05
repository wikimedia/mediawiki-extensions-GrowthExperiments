var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	AddImageSaveDialog = StructuredTask.AddImageSaveDialog;

/**
 * Replacement for the normal VE Save / Publish dialog that replaces the freetext
 * edit summary with a structured summary, and records structured data about the edit.
 *
 * @class mw.libs.ge.ui.AddImageDesktopSaveDialog
 * @extends ve.ui.MWSaveDialog
 * @mixes mw.libs.ge.ui.AddImageSaveDialog
 *
 * @param {Object} [config] Config options
 * @constructor
 */
function AddImageDesktopSaveDialog( config ) {
	AddImageDesktopSaveDialog.super.call( this, config );
	AddImageSaveDialog.call( this, config );
}

OO.inheritClass( AddImageDesktopSaveDialog, ve.ui.MWSaveDialog );
OO.mixinClass( AddImageDesktopSaveDialog, AddImageSaveDialog );

module.exports = AddImageDesktopSaveDialog;
