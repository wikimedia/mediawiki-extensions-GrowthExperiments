/**
 * @class mw.libs.ge.dm.RecommendedLinkAnnotation
 * @extends ve.dm.MWInternalLinkAnnotation
 * @constructor
 */
const DMRecommendedLinkAnnotation = function GeDmRecommendedLinkAnnotation() {
	// Parent constructor
	DMRecommendedLinkAnnotation.super.apply( this, arguments );

};

OO.inheritClass( DMRecommendedLinkAnnotation, ve.dm.MWInternalLinkAnnotation );

DMRecommendedLinkAnnotation.static.name = 'mwGeRecommendedLink';

DMRecommendedLinkAnnotation.static.matchTagNames = [ 'span' ];

DMRecommendedLinkAnnotation.static.matchRdfaTypes = [ 'mw:RecommendedLink' ];

DMRecommendedLinkAnnotation.static.allowedRdfaTypes = [];

DMRecommendedLinkAnnotation.static.toDataElement = function ( domElements ) {
	const target = domElements[ 0 ].getAttribute( 'data-target' ),
		text = domElements[ 0 ].getAttribute( 'data-text' ),
		wikitextOffset = domElements[ 0 ].getAttribute( 'data-wikitext-offset' ),
		score = domElements[ 0 ].getAttribute( 'data-score' ),
		title = mw.Title.newFromText( target );

	if ( !title ) {
		// We would like to not wrap the text in an annotation at all, but ve.dm.Converter
		// doesn't offer that option. We also can't have the CE class render the text as a plain
		// span, because it has .static.canBeActive (via ve.ce.NailedAnnotation) and there's no
		// way to dynamically disable that.
		return { type: 'mwGeRecommendedLinkError' };
	}
	// Let the parent class build the attributes as if this is a real link
	const dataElement = this.dataElementFromTitle( title, target );
	// true means accepted; false means explicitly rejected; null means no selection made yet
	dataElement.attributes.recommendationAccepted = null;
	// undefined means no reason supplied, otherwise this is a string.
	dataElement.attributes.rejectionReason = undefined;
	dataElement.attributes.recommendationWikitextOffset = wikitextOffset;
	dataElement.attributes.score = score;
	dataElement.attributes.text = text;
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

/** @inheritDoc */
DMRecommendedLinkAnnotation.static.describeChange = function () {
	// We don't want to show change descriptions, given that the diff will not contain
	// any other kind of change it's not really useful.
};

/** @inheritdoc */
DMRecommendedLinkAnnotation.prototype.getComparableObject = function () {
	return {
		type: this.getType(),
		normalizedTitle: this.getAttribute( 'normalizedTitle' ),
		accepted: this.isAccepted(),
	};
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

DMRecommendedLinkAnnotation.prototype.getRejectionReason = function () {
	return this.getAttribute( 'rejectionReason' );
};

/**
 * Return acceptance state of the annotation as a string
 *
 * @return {string}
 */
DMRecommendedLinkAnnotation.prototype.getState = function () {
	if ( this.isAccepted() ) {
		return 'accepted';
	} else if ( this.isRejected() ) {
		return 'rejected';
	} else {
		return 'undecided';
	}
};

module.exports = DMRecommendedLinkAnnotation;
