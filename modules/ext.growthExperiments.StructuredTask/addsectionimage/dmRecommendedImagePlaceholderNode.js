/**
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
			height: domElements[ 0 ].getAttribute( 'data-height' )
		}
	};
};

/** @inheritDoc */
DMRecommendedImagePlaceholderNode.static.toDomElements = function ( dataElement, doc ) {
	var div = doc.createElement( 'div' );
	div.dataset.width = dataElement.attributes.width;
	div.dataset.height = dataElement.attributes.height;
	div.setAttribute( 'typeof', 'mw:GeRecommendedImagePlaceholder' );
	return [ div ];
};

module.exports = DMRecommendedImagePlaceholderNode;
