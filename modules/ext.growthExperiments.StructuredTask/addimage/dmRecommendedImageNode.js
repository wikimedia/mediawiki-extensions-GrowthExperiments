/**
 * Own attributes:
 * - recommendation: {@see mw.libs.ge.dm.Recommendation}
 * - recommendationIndex: {number} Zero-based index of the selected image suggestion.
 *
 * @class mw.libs.ge.dm.RecommendedImageNode
 * @extends ve.dm.MWBlockImageNode
 * @constructor
 */
function DMRecommendedImageNode() {
	DMRecommendedImageNode.super.apply( this, arguments );
}

OO.inheritClass( DMRecommendedImageNode, ve.dm.MWBlockImageNode );

DMRecommendedImageNode.static.name = 'mwGeRecommendedImage';
DMRecommendedImageNode.static.childNodeTypes = [ 'mwGeRecommendedImageCaption' ];

/** @inheritDoc **/
DMRecommendedImageNode.static.matchFunction = function ( element ) {
	// DMRecommendedImageNode inherits matchTagNames from ve.dm.MWBlockImageNode so figure elements
	// already in the article will be a match candidate. Additional class name check ensures that
	// existing images in the article don't get treated as a suggested image.
	const hasImage = ve.dm.BlockImageNode.static.matchFunction( element );
	return hasImage && element.classList.includes( 'mw-ge-recommendedImage' );
};

module.exports = DMRecommendedImageNode;
