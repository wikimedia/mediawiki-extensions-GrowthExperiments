/**
 * ContentEditable node for the recommended image placeholder. It tries to simulate the
 * positioning and dimensions of the eventual image.
 *
 * @class mw.libs.ge.ce.RecommendedImagePlaceholderNode
 * @extends ve.ce.LeafNode
 * @constructor
 */
function CERecommendedImagePlaceholderNode() {
	CERecommendedImagePlaceholderNode.super.apply( this, arguments );
	this.$element
		.css( {
			// width is the sum of:
			// - 2x8px padding on desktop
			// - - 2x16px margin on mobile
			// - the image
			width: this.model.getAttribute( 'width' ) + ( OO.ui.isMobile() ? -32 : 16 ),
			// height is the sum of:
			// - 2x7px padding on desktop / 2x9.6px padding on mobile
			// - header (desktop only): 32 px icon height + 8px bottom margin
			// - the image
			// - caption: 4px/6.5px (desktop/mobile) top margin + 2x2 px border + 7+27px padding
			//   + content height (which we are guessing here as 60px).
			height: this.model.getAttribute( 'height' ) + ( OO.ui.isMobile() ? 130 : 156 ),
		} )
		.addClass( 'ge-section-image-placeholder' )
		// mimic MediaWiki's default image alignment behavior, like ve.ce.MWBlockImageNode.getCssClass
		.addClass( this.getModel().getDocument().getDir() === 'rtl' ? 'ge-align-left' : 'ge-align-right' )
		// a somewhat hacky way of adding the 'image' OOUI icon (progressive variant) as background
		.addClass( 'oo-ui-image-progressive oo-ui-icon-image' );
}
OO.inheritClass( CERecommendedImagePlaceholderNode, ve.ce.LeafNode );

/** @inheritDoc */
CERecommendedImagePlaceholderNode.static.name = 'mwGeRecommendedImagePlaceholder';

/** @inheritDoc */
CERecommendedImagePlaceholderNode.static.tagName = 'div';

module.exports = CERecommendedImagePlaceholderNode;
