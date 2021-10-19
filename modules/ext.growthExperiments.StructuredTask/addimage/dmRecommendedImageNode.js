/**
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

module.exports = DMRecommendedImageNode;
