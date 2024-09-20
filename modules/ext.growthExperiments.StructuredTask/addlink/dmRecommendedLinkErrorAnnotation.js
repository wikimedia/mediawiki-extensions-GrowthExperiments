/**
 * @class mw.libs.ge.dm.RecommendedLinkErrorAnnotation
 * @extends ve.dm.Annotation
 * @constructor
 */
const DMRecommendedLinkErrorAnnotation = function GeDmRecommendedLinkErrorAnnotation() {
	// Parent constructor
	DMRecommendedLinkErrorAnnotation.super.apply( this, arguments );

};

OO.inheritClass( DMRecommendedLinkErrorAnnotation, ve.dm.Annotation );

DMRecommendedLinkErrorAnnotation.static.name = 'mwGeRecommendedLinkError';

DMRecommendedLinkErrorAnnotation.static.matchTagNames = [];

DMRecommendedLinkErrorAnnotation.static.toDomElements = function () {
	// Unwrap
	return [];
};

module.exports = DMRecommendedLinkErrorAnnotation;
