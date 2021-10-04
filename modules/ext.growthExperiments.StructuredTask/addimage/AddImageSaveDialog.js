var ImageSuggestionInteractionLogger = require( './ImageSuggestionInteractionLogger.js' ),
	StructuredTaskSaveDialog = require( '../StructuredTaskSaveDialog.js' );

/**
 * Mixin for code sharing between AddImageDesktopSaveDialog and AddImageMobileSaveDialog.
 * This is to solve the diamond inheritance problem of ve.ui.MWSaveDialog -->
 * AddImageSaveDialog and ve.ui.MWSaveDialog --> ve.ui.MWDesktopSaveDialog.
 *
 * @mixin mw.libs.ge.ui.AddImageSaveDialog
 * @extends ve.ui.MWSaveDialog
 * @mixes mw.libs.ge.ui.StructuredTaskSaveDialog
 *
 * @constructor
 */
function AddImageSaveDialog() {
	StructuredTaskSaveDialog.call( this );
	this.$element.addClass( 'ge-addimage-mwSaveDialog' );
	this.logger = new ImageSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'editsummary_dialog'
		/* eslint-enable camelcase */
	} );
}

OO.initClass( AddImageSaveDialog );
OO.mixinClass( AddImageSaveDialog, StructuredTaskSaveDialog );

/** @inheritDoc */
AddImageSaveDialog.prototype.getActionProcess = function ( action ) {
	return StructuredTaskSaveDialog.prototype.getActionProcess.call( this, action ).next( function () {
		// When the save is cancelled, also cancel adding the image.
		if ( action === '' ) {
			ve.init.target.rollback();
		}
	}, this );
};

module.exports = AddImageSaveDialog;
