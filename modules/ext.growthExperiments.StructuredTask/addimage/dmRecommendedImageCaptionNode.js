/**
 * Own attributes:
 * - taskType: {string} Task type ID of the task.
 * - visibleSectionTitle: {string} Section title of the section where the image is inserted.
 *   Plain text, but based on the rendered HTML version of the section title (so e.g.
 *   LanguageConverter rules have been applied).
 *
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
