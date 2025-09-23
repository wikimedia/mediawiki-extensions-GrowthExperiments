const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	AddImageClasses = StructuredTask.addImage(),
	AddImageSaveDialog = AddImageClasses.AddImageSaveDialog,
	AddImageUtils = AddImageClasses.AddImageUtils,
	IMAGE_PREVIEW_WIDTH = 226;

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
	this.$element.addClass( 'mw-ge-addImageSaveDialog-desktop' );
}

OO.inheritClass( AddImageDesktopSaveDialog, ve.ui.MWSaveDialog );
OO.mixinClass( AddImageDesktopSaveDialog, AddImageSaveDialog );

/** @inheritDoc **/
AddImageDesktopSaveDialog.prototype.getImagePreview = function ( summaryData ) {
	const $imagePreview = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imagePreview' ),
		imageRenderData = AddImageUtils.getImageRenderData(
			summaryData.metadata,
			window,
			IMAGE_PREVIEW_WIDTH,
		);
	$imagePreview.css( {
		backgroundImage: 'url("' + imageRenderData.src + '")',
		width: IMAGE_PREVIEW_WIDTH + 'px',
	} );
	return $imagePreview;
};

module.exports = AddImageDesktopSaveDialog;
