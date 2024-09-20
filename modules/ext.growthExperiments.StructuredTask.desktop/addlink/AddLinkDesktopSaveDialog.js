const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	AddLinkSaveDialog = StructuredTask.addLink().AddLinkSaveDialog;

/**
 * Replacement for the normal VE Save / Publish dialog that replaces the freetext
 * edit summary with a structured summary, and records structured data about the edit.
 *
 * @class mw.libs.ge.ui.AddLinkDesktopSaveDialog
 * @extends ve.ui.MWSaveDialog
 * @mixes mw.libs.ge.ui.AddLinkSaveDialog
 *
 * @param {Object} [config] Config options
 * @constructor
 */
function AddLinkDesktopSaveDialog( config ) {
	AddLinkDesktopSaveDialog.super.call( this, config );
	AddLinkSaveDialog.call( this, config );
}

OO.inheritClass( AddLinkDesktopSaveDialog, ve.ui.MWSaveDialog );
OO.mixinClass( AddLinkDesktopSaveDialog, AddLinkSaveDialog );

module.exports = AddLinkDesktopSaveDialog;
