/**
 * A placeholder that shows where the suggested image will be inserted if the user accepts it.
 * It takes up the same place in the linear model that the image will; it doesn't have any
 * content or behavior of its own.
 *
 * Own attributes:
 * - width: {number} Width of the image in pixels.
 * - height: {number} Height of the image in pixels.
 *
 * @class mw.libs.ge.dm.RecommendedImagePlaceholderNode
 * @extends ve.dm.MWBlockImageNode
 * @constructor
 */
function DMRecommendedImagePlaceholderNode() {
	DMRecommendedImagePlaceholderNode.super.apply( this, arguments );
}
OO.inheritClass( DMRecommendedImagePlaceholderNode, ve.dm.LeafNode );

/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.name = 'mwGeRecommendedImagePlaceholder';

// This is the default value; repeat for readability.
/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.isContent = false;

/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.toDomElements = function () {
	// No output - the page might be saved with the placeholder in it, and we don't want to
	// mess up wikitext when that happens. (T340170)
	return [];
};

// These are all fake - the output of toDomElements() is empty so there is nothing to match.
// Restoring the linear model from HTML would not work, but it's only done on a wikitext -> VE
// switch and we don't support that in structured editing workflows.
/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.matchTagNames = [ 'div' ];
/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.matchRdfaTypes = [ 'mw:GeRecommendedImagePlaceholder' ];
DMRecommendedImagePlaceholderNode.static.preserveHtmlAttributes = false;
/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.toDataElement = function ( domElements ) {
	return {
		type: this.name,
		attributes: {
			width: domElements[ 0 ].getAttribute( 'data-width' ),
			height: domElements[ 0 ].getAttribute( 'data-height' ),
		},
	};
};

module.exports = DMRecommendedImagePlaceholderNode;
