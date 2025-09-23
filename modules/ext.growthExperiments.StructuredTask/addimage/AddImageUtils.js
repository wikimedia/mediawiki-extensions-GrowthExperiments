( function () {

	/**
	 * @typedef {Object} mw.libs.ge.RecommendedImageRenderData
	 *
	 * @property {string} src URL for the image (resized for rendering in the current viewport)
	 * @property {number} maxWidth Maximum width at which the image should be rendered
	 */

	/**
	 * Get the thumbnail URL for image source and the max width.
	 * If the computed source width (render width * device pixel ratio) is larger than the original
	 * width, the original full URL will be returned, else the thumbnail URL will be returned.
	 *
	 * @param {mw.libs.ge.RecommendedImageMetadata} metadata
	 * @param {Window} viewport
	 * @param {number} [renderWidth] Intended width at which the image is rendered (does not take
	 *  into account the device pixel ratio); if not specified, the viewport width is used.
	 *
	 * @return {mw.libs.ge.RecommendedImageRenderData} renderData
	 */
	function getImageRenderData( metadata, viewport, renderWidth ) {
		const thumb = mw.util.parseImageUrl( metadata.thumbUrl ) || {},
			originalWidth = metadata.originalWidth;
		let imageSrc = metadata.fullUrl,
			maxWidth = renderWidth || originalWidth;

		// The file is a thumbnail and can be resized.
		if ( thumb.width && thumb.resizeUrl ) {
			const aspectRatio = metadata.originalWidth / metadata.originalHeight;

			if ( !renderWidth ) {
				renderWidth = Math.min( viewport.innerWidth, viewport.innerHeight * aspectRatio );
			}
			const targetSrcWidth = Math.floor( viewport.devicePixelRatio * renderWidth );

			// The image should be resized if the target source width is smaller than the original
			// or if the file needs to be re-rasterized (resizeUrl only works if the target width is
			// smaller than the original image width). For vectors, the thumbnail can be used since
			// the asset dimension doesn't really matter.
			if ( targetSrcWidth < originalWidth || metadata.isVectorized ) {
				imageSrc = thumb.resizeUrl( targetSrcWidth );
				maxWidth = Math.floor( renderWidth );
			} else if ( metadata.mustRender ) {
				imageSrc = thumb.resizeUrl( originalWidth );
				maxWidth = Math.min( Math.floor( renderWidth ), originalWidth );
			}
		}
		return {
			src: imageSrc,
			maxWidth: maxWidth,
		};
	}

	module.exports = {
		getImageRenderData: getImageRenderData,
	};

}() );
