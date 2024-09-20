/**
 * @class mw.libs.ge.ce.CERecommendedLinkErrorAnnotation
 * @extends ve.ce.Annotation
 * @constructor
 */
const CERecommendedLinkErrorAnnotation = function GeCeRecommendedLinkErrorAnnotation() {
	// Parent constructor
	CERecommendedLinkErrorAnnotation.super.apply( this, arguments );

	this.$element.addClass( 'mw-ge-recommendedLinkAnnotation' );
};

OO.inheritClass( CERecommendedLinkErrorAnnotation, ve.ce.Annotation );

CERecommendedLinkErrorAnnotation.static.name = 'mwGeRecommendedLinkError';

CERecommendedLinkErrorAnnotation.static.tagName = 'span';

module.exports = CERecommendedLinkErrorAnnotation;
