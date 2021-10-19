/**
 * @class mw.libs.ge.ce.RecommendedImageNode
 * @extends ve.ce.MWBlockImageNode
 * @constructor
 */
function CERecommendedImageNode() {
	CERecommendedImageNode.super.apply( this, arguments );
	/**
	 * @property {boolean} isImageReady Whether the image source has been loaded
	 */
	this.isImageReady = false;
	/**
	 * @property {boolean} isImageShown Whether the image (as opposed to the loading state) is shown
	 */
	this.isImageShown = false;
	/**
	 * @property {boolean} shouldShowImageAfterLoad Whether to show the image right after loading
	 */
	this.shouldShowImageAfterLoad = false;
	/**
	 * @property {number} loadingDelay Minimum time to wait before showing the image
	 * This is so that the loading state doesn't abruptly disappear if the image loads quickly.
	 */
	this.loadingDelay = 500;
	this.setupLoadingOverlay();
	this.showImageLoadingState();
	setTimeout( function () {
		// If the image loads before the delay, show it when the delay is over.
		if ( this.isImageReady ) {
			this.showImage();
		} else {
			// Show the image right after it's done loading since the delay is over the delay
			this.shouldShowImageAfterLoad = true;
		}
	}.bind( this ), this.loadingDelay );
}

OO.inheritClass( CERecommendedImageNode, ve.ce.MWBlockImageNode );

CERecommendedImageNode.static.name = 'mwGeRecommendedImage';

/**
 * Append an overlay to be shown when the image is loading
 */
CERecommendedImageNode.prototype.setupLoadingOverlay = function () {
	this.$loadingOverlay = $( '<div>' ).addClass( 'mw-ge-recommendedImage-loading-overlay' );
	this.$element.addClass( 'mw-ge-recommendedImage' ).css( {
		width: this.model.getAttribute( 'width' )
	} ).append( this.$loadingOverlay );
};

/**
 * Show loading state with the actual image height
 */
CERecommendedImageNode.prototype.showImageLoadingState = function () {
	var $img = this.$element.find( 'img' ),
		onImageLoad = function () {
			this.isImageReady = true;
			if ( this.shouldShowImageAfterLoad ) {
				this.showImage();
			}
		}.bind( this );
	$img.css( { minHeight: this.model.getAttribute( 'height' ) } );
	$img.on( 'load', onImageLoad );
	// In case load event isn't fired or if somehow the image failed to load
	setTimeout( onImageLoad, 5000 );
};

/**
 * Show the image and fire an event so that the toolbar can be updated; do nothing if the image is
 * already shown
 */
CERecommendedImageNode.prototype.showImage = function () {
	if ( this.isImageShown ) {
		return;
	}
	mw.hook( 'growthExperiments.imageSuggestions.onImageCaptionReady' ).fire();
	this.$loadingOverlay.addClass( 'mw-ge-recommendedImage-loading-overlay--image-shown' );
	this.isImageShown = true;
	this.setupDetailsButton( this.$element.find( '.image' ) );
};

/**
 * Set up view details button
 *
 * @param {jQuery} $container Container in which to append the details button
 */
CERecommendedImageNode.prototype.setupDetailsButton = function ( $container ) {
	var $detailsButton = $( '<div>' ).addClass( 'mw-ge-recommendedImage-details-button' )
		.attr( 'role', 'button' )
		.append( [
			new OO.ui.IconWidget( {
				framed: false,
				classes: [ 'mw-ge-recommendedImage-details-icon' ],
				icon: 'infoFilled'
			} ).$element,
			mw.message( 'growthexperiments-addimage-inspector-details-button' ).escaped()
		] );
	$detailsButton.on( 'click', function () {
		// TODO: Show image details dialog (T290782)
	} );
	$container.append( $detailsButton );
};

/**
 * Disable resizing (on desktop, the image node can be resized by dragging it)
 *
 * @override
 * @return {boolean}
 */
CERecommendedImageNode.prototype.isResizable = function () {
	return false;
};

module.exports = CERecommendedImageNode;
