/**
 * @class mw.libs.ge.ce.RecommendedImageNode
 * @extends ve.ce.MWBlockImageNode
 * @constructor
 */
function CERecommendedImageNode() {
	CERecommendedImageNode.super.apply( this, arguments );
}

OO.inheritClass( CERecommendedImageNode, ve.ce.MWBlockImageNode );

CERecommendedImageNode.static.name = 'mwGeRecommendedImage';

module.exports = CERecommendedImageNode;
