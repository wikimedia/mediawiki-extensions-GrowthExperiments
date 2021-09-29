var router = require( 'mediawiki.router' );

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
		classes: [ 'mw-ge-recommendedImageViewer-image-close-button' ]
	} );
	this.closeButton.on( 'click', function () {
		router.back();
	} );
	this.$head.append( this.closeButton.$element );
	this.$body.append( this.$image );
};

/**
 * @typedef {Object} mw.libs.ge.RecommendedImageRenderData
 *
 * @property {string} src URL for the image (resized for rendering in the current viewport)
 * @property {number} maxWidth Maximum width at which the image should be rendered
 */

/**
 * Get the URL for image source and the max width based on the provided metadata and the viewport
 *
 * @param {mw.libs.ge.RecommendedImageMetadata} metadata
 * @param {Window} viewport
 * @return { mw.libs.ge.RecommendedImageRenderData} renderData
 */
RecommendedImageViewer.prototype.getRenderData = function ( metadata, viewport ) {
	var thumb = mw.util.parseImageUrl( metadata.thumbUrl ) || {},
		imageSrc = metadata.fullUrl,
		originalWidth = metadata.originalWidth,
		maxWidth = originalWidth;

	// The file is a thumbnail and can be resized.
	if ( thumb.width && thumb.resizeUrl ) {
		var aspectRatio = metadata.originalWidth / metadata.originalHeight,
			targetWidth = Math.min( viewport.innerWidth, viewport.innerHeight * aspectRatio ),
			targetSrcWidth = Math.floor( viewport.devicePixelRatio * targetWidth );

		// The image should be resized if the target width is smaller than the original
		// or if the file needs to be re-rasterized. For vectors, the thumbnail can be used since
		// the asset dimension doesn't really matter.
		if ( targetSrcWidth < originalWidth ||
			( targetSrcWidth === originalWidth && metadata.mustRender ) ||
			metadata.isVectorized ) {
			imageSrc = thumb.resizeUrl( targetSrcWidth );
			maxWidth = Math.floor( targetWidth );
		}
	}
	return {
		src: imageSrc,
		maxWidth: maxWidth
	};
};

/**
 * Show the specified image
 *
 * @param {mw.libs.ge.RecommendedImageMetadata} metadata
 */
RecommendedImageViewer.prototype.updateImage = function ( metadata ) {
	// TODO: image caption as alt text?
	var renderData = this.getRenderData( metadata, window );
	this.$image.attr( { src: renderData.src } );
	this.$image.css( 'max-width', renderData.maxWidth + 'px' );
};

/** @inheritDoc **/
RecommendedImageViewer.prototype.getSetupProcess = function ( data ) {
	return RecommendedImageViewer.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateImage( data );
		}, this );
};

module.exports = RecommendedImageViewer;
