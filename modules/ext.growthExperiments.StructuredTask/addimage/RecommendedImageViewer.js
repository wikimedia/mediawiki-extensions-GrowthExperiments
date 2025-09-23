const router = require( 'mediawiki.router' ),
	AddImageUtils = require( './AddImageUtils.js' );

/**
 * Dialog for viewing the recommended image in full screen
 *
 * @class mw.libs.ge.RecommendedImageViewer
 * @extends OO.ui.Dialog
 * @constructor
 */
function RecommendedImageViewer() {
	RecommendedImageViewer.super.apply( this, arguments );
	this.$element.addClass( 'mw-ge-recommendedImageViewer' );
}

OO.inheritClass( RecommendedImageViewer, OO.ui.Dialog );

RecommendedImageViewer.static.name = 'recommendedImageViewer';
RecommendedImageViewer.static.size = 'full';

/** @inheritDoc **/
RecommendedImageViewer.prototype.initialize = function () {
	RecommendedImageViewer.super.prototype.initialize.call( this );
	this.$image = $( '<img>' ).addClass( 'mw-ge-recommendedImageViewer-image' );
	this.closeButton = new OO.ui.ButtonWidget( {
		icon: 'close-shadow',
		framed: false,
		label: mw.message( 'growthexperiments-addimage-viewer-close-button' ).text(),
		invisibleLabel: true,
		classes: [ 'mw-ge-recommendedImageViewer-image-close-button' ],
	} );
	this.closeButton.on( 'click', () => {
		router.back();
	} );
	this.$head.append( this.closeButton.$element );
	this.$body.append( this.$image );
};

/**
 * Show the specified image
 *
 * @param {mw.libs.ge.RecommendedImageMetadata} metadata
 */
RecommendedImageViewer.prototype.updateImage = function ( metadata ) {
	// TODO: image caption as alt text?
	const renderData = AddImageUtils.getImageRenderData( metadata, window );
	this.$image.attr( { src: renderData.src } );
	this.$image.css( 'max-width', 'min(100%,' + renderData.maxWidth + 'px)' );
};

/** @inheritDoc **/
RecommendedImageViewer.prototype.getSetupProcess = function ( data ) {
	return RecommendedImageViewer.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateImage( data );
		}, this );
};

module.exports = RecommendedImageViewer;
