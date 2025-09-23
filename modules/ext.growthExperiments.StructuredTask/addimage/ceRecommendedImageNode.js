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
	/**
	 * @property {number} Offset value to use when scrolling to the caption field
	 */
	this.scrollOffset = 100;
	/**
	 * @property {mw.libs.ge.AddImageArticleTarget} articleTarget
	 */
	this.articleTarget = ve.init.target;
	if ( !OO.ui.isMobile() ) {
		this.setupHeader();
	}
	this.setupLoadingOverlay();
	this.showImageLoadingState();
	setTimeout( () => {
		// If the image loads before the delay, show it when the delay is over.
		if ( this.isImageReady ) {
			this.showImage();
		} else {
			// Show the image right after it's done loading since the delay is over the delay
			this.shouldShowImageAfterLoad = true;
		}
	}, this.loadingDelay );
	this.$a.addClass( 'mw-ge-recommendedImage-imageWrapper' );
}

OO.inheritClass( CERecommendedImageNode, ve.ce.MWBlockImageNode );

CERecommendedImageNode.static.name = 'mwGeRecommendedImage';

/**
 * Append header and delete button
 */
CERecommendedImageNode.prototype.setupHeader = function () {
	const router = require( 'mediawiki.router' ),
		deleteButton = new OO.ui.ButtonWidget( {
			icon: 'trash',
			framed: false,
			classes: [ 'mw-ge-recommendedImage-deleteButton' ],
		} ),
		$header = $( '<div>' ).addClass( 'mw-ge-recommendedImage-header' ).text(
			mw.message( 'growthexperiments-addimage-caption-title' ).text(),
		).append( deleteButton.$element );
	deleteButton.on( 'click', () => {
		router.back();
	} );
	this.$element.prepend( $header );
};

/**
 * Append an overlay to be shown when the image is loading
 */
CERecommendedImageNode.prototype.setupLoadingOverlay = function () {
	this.$loadingOverlay = $( '<div>' ).addClass( 'mw-ge-recommendedImage-loading-overlay' );
	this.$element.addClass( 'mw-ge-recommendedImage' ).append( this.$loadingOverlay );
};

/**
 * Show loading state with the actual image height
 */
CERecommendedImageNode.prototype.showImageLoadingState = function () {
	const imageWidth = this.model.getAttribute( 'width' ),
		$img = this.$element.find( 'img' ),
		onImageLoad = function () {
			this.isImageReady = true;
			if ( this.shouldShowImageAfterLoad ) {
				this.showImage();
			}
		}.bind( this );
	this.$element.css( { width: imageWidth } );
	$img.css( {
		width: imageWidth,
		minHeight: this.model.getAttribute( 'height' ),
	} );
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
	this.setupDetailsButton( this.$a );
	setTimeout( () => {
		if ( OO.ui.isMobile() ) {
			// Scroll the page so that the details button is near the top of the screen.
			// This helps keep the key elements (the caption edit area and the details button)
			// visible and not overlapped by the keyboard.
			this.articleTarget.surface.$scrollContainer.animate( {
				scrollTop: this.$detailsButton.offset().top - this.scrollOffset,
			} );
		} else {
			// If the user scrolled away while examining the article, scroll the caption field
			// and details button back into view. Don't scroll if we don't have to. Don't try to
			// scroll the entire image into view, just in case it's very tall and that would lead
			// to the caption are being hidden.
			OO.ui.Element.static.scrollIntoView( this.$detailsButton[ 0 ], {
				animate: true,
				direction: 'y',
				padding: {
					top: this.scrollOffset,
					// Approximate caption area height, to ensure it's fully in view:
					// 102px for the figcaption element including margins (see
					// CERecommendedImagePlaceholderNode() for details), 24px for the
					// image node's bottom padding/border/margin.
					// FIXME unlike in the placeholder, we could just get the actual height here
					bottom: 126,
				},
				duration: 400,
				scrollContainer: this.articleTarget.surface.$scrollContainer[ 0 ],
			} );
		}
	}, 300 );
};

/**
 * Set up view details button
 *
 * @param {jQuery} $container Container in which to append the details button
 */
CERecommendedImageNode.prototype.setupDetailsButton = function ( $container ) {
	this.$detailsButton = $( '<div>' ).addClass( 'mw-ge-recommendedImage-detailsButton' )
		.attr( 'role', 'button' )
		.append( [
			new OO.ui.IconWidget( {
				framed: false,
				classes: [ 'mw-ge-recommendedImage-detailsIcon' ],
				icon: 'infoFilled',
			} ).$element,
			mw.message( 'growthexperiments-addimage-inspector-details-button' ).escaped(),
		] );
	// Clicking anywhere in the image brings up the details dialog.
	$container.on( 'click', () => {
		const surface = this.getRoot().getSurface().getSurface(),
			recommendation = this.getModel().getAttribute( 'recommendation' ),
			recommendationIndex = this.getModel().getAttribute( 'recommendationIndex' );
		surface.dialogs.openWindow( 'addImageDetails', {
			recommendation: recommendation,
			logSource: 'caption_entry',
			imageIndex: recommendationIndex,
		} );
	} );
	$container.append( this.$detailsButton );
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

/** @override */
CERecommendedImageNode.prototype.updateSize = function () {
	// Intentionally no-op since resizing is not allowed
};

module.exports = CERecommendedImageNode;
