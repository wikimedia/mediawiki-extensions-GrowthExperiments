/**
 * @class mw.libs.ge.ce.RecommendedImageCaptionNode
 * @extends ve.ce.MWImageCaptionNode
 * @constructor
 */
function CERecommendedImageCaptionNode() {
	CERecommendedImageCaptionNode.super.apply( this, arguments );
	this.$element.addClass( 'mw-ge-recommendedImageCaptionNode' );
}

OO.inheritClass( CERecommendedImageCaptionNode, ve.ce.MWImageCaptionNode );

CERecommendedImageCaptionNode.static.name = 'mwGeRecommendedImageCaption';

module.exports = CERecommendedImageCaptionNode;
