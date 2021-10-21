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

/**
 * Check whether there's any text in the caption field.
 * This is called from mw.libs.ge.ce.AddImageLinearDeleteKeyDownHandler to determine whether the
 * default delete handler should be called.
 *
 * @return {boolean}
 */
CERecommendedImageCaptionNode.prototype.isEmpty = function () {
	// The caption content is held in VeDmParagraphNode.
	return this.model.getChildren()[ 0 ].getLength() === 0;
};

module.exports = CERecommendedImageCaptionNode;
