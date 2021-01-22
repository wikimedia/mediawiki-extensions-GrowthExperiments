/**
 * @class mw.libs.ge.ce.RecommendedLinkAnnotation
 * @extends ve.ce.MWInternalLinkAnnotation
 * @constructor
 */
function CERecommendedLinkAnnotation() {
	// Parent constructor
	CERecommendedLinkAnnotation.super.apply( this, arguments );

	this.$element.addClass( 'mw-ge-recommendedLinkAnnotation' );
}

OO.inheritClass( CERecommendedLinkAnnotation, ve.ce.MWInternalLinkAnnotation );

CERecommendedLinkAnnotation.static.name = 'mwGeRecommendedLink';

CERecommendedLinkAnnotation.prototype.updateClasses = function () {
	// Don't call the parent: we don't want redlink styling, and we don't want to write to
	// the linkCache
	if ( this.model.isAccepted() ) {
		this.$element.addClass( 'mw-ge-recommendedLinkAnnotation-accepted' );
	} else if ( this.model.isRejected() ) {
		this.$element.addClass( 'mw-ge-recommendedLinkAnnotation-rejected' );
	} else {
		this.$element.addClass( 'mw-ge-recommendedLinkAnnotation-undecided' );
	}
};

module.exports = CERecommendedLinkAnnotation;
