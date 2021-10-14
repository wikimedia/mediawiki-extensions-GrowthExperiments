/**
 * @class mw.libs.ge.dm.RecommendedImageCaptionNode
 * @extends ve.dm.MWImageCaptionNode
 * @constructor
 */
function DMRecommendedImageCaptionNode() {
	DMRecommendedImageCaptionNode.super.apply( this, arguments );
}

OO.inheritClass( DMRecommendedImageCaptionNode, ve.dm.MWImageCaptionNode );

DMRecommendedImageCaptionNode.static.name = 'mwGeRecommendedImageCaption';
DMRecommendedImageCaptionNode.static.parentNodeTypes = [ 'mwGeRecommendedImage' ];

module.exports = DMRecommendedImageCaptionNode;
