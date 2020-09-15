/**
 * @class mw.libs.ge.dm.RecommendedLinkAnnotation
 * @extends ve.dm.MWInternalLinkAnnotation
 * @constructor
 */
var DMRecommendedLinkAnnotation = function GeDmRecommendedLinkAnnotation() {
	// Parent constructor
	DMRecommendedLinkAnnotation.super.apply( this, arguments );

};

OO.inheritClass( DMRecommendedLinkAnnotation, ve.dm.MWInternalLinkAnnotation );

DMRecommendedLinkAnnotation.static.name = 'mwGeRecommendedLink';

DMRecommendedLinkAnnotation.static.matchTagNames = [ 'span' ];

DMRecommendedLinkAnnotation.static.matchRdfaTypes = [ 'mw:RecommendedLink' ];

DMRecommendedLinkAnnotation.static.allowedRdfaTypes = [];

DMRecommendedLinkAnnotation.static.toDataElement = function ( domElements, converter ) {
	var target = domElements[ 0 ].getAttribute( 'data-target' ),
		title = mw.Title.newFromText( target ),
		dataElement;
	if ( !title ) {
		// We would like to not wrap the text in an annotation at all, but ve.dm.Converter
		// doesn't offer that option. We also can't have the CE class render the text as a plain
		// span, because it has .static.canBeActive (via ve.ce.NailedAnnotation) and there's no
		// way to dynamically disable that.
		return { type: 'mwGeRecommendedLinkError' };
	}
	// Let the parent class build the attributes as if this is a real link
	dataElement = this.dataElementFromTitle( title, target );
	// true means accepted; false means explicitly rejected; null means no selection made yet
	dataElement.attributes.recommendationAccepted = null;
	dataElement.attributes.recommendationId = String( converter.internalList.getNextUniqueNumber() );
	return dataElement;
};

DMRecommendedLinkAnnotation.static.toDomElements = function ( dataElement ) {
	if ( !dataElement.attributes.recommendationAccepted ) {
		// Not accepted: either false (explicitly rejected) or null (no decision)
		// Unwrap
		return [];
	}

	// Let the parent class handle this; it will be able to since this annotation has all the
	// attributes of a real links
	return DMRecommendedLinkAnnotation.super.static.toDomElements.apply( this, arguments );
};

DMRecommendedLinkAnnotation.prototype.isAccepted = function () {
	return this.getAttribute( 'recommendationAccepted' ) === true;
};

DMRecommendedLinkAnnotation.prototype.isRejected = function () {
	return this.getAttribute( 'recommendationAccepted' ) === false;
};

DMRecommendedLinkAnnotation.prototype.isUndecided = function () {
	return this.getAttribute( 'recommendationAccepted' ) === null;
};

module.exports = DMRecommendedLinkAnnotation;
