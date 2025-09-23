const AnnotationAnimation = require( '../AnnotationAnimation.js' );

/**
 * @class mw.libs.ge.ce.RecommendedLinkAnnotation
 * @extends ve.ce.MWInternalLinkAnnotation
 * @constructor
 */
function CERecommendedLinkAnnotation() {
	// Parent constructor
	CERecommendedLinkAnnotation.super.apply( this, arguments );
	this.isActive = false;

	this.$element.addClass( [
		'mw-ge-recommendedLinkAnnotation',
		OO.ui.isMobile() ? 'mw-ge-recommendedLinkAnnotation-mobile' : 'mw-ge-recommendedLinkAnnotation-desktop',
	] );
	// Prevent flashing from inactive to active states when updating annotation without auto-advance
	if ( !OO.ui.isMobile() ) {
		this.updateActiveClass( this.isRenderingUpdatedAnnotation() );
	}
}

OO.inheritClass( CERecommendedLinkAnnotation, ve.ce.MWInternalLinkAnnotation );

CERecommendedLinkAnnotation.static.name = 'mwGeRecommendedLink';
CERecommendedLinkAnnotation.static.canBeActive = true;

/**
 * @inheritdoc
 */
CERecommendedLinkAnnotation.prototype.updateClasses = function () {
	// Don't call the parent: we don't want redlink styling, and we don't want to write to
	// the linkCache
	// The following classes are used here:
	// * mw-ge-recommendedLinkAnnotation-accepted
	// * mw-ge-recommendedLinkAnnotation-rejected
	// * mw-ge-recommendedLinkAnnotation-undecided
	this.$element.addClass( 'mw-ge-recommendedLinkAnnotation-' + this.model.getState() );
};

/**
 * Update active states on the annotation
 *
 * @param {boolean} isActive
 */
CERecommendedLinkAnnotation.prototype.updateActiveClass = function ( isActive ) {
	this.isActive = isActive;
	this.$element.toggleClass( 'mw-ge-recommendedLinkAnnotation-active', isActive );
};

/**
 * @inheritdoc
 */
CERecommendedLinkAnnotation.prototype.onClick = function ( e ) {
	CERecommendedLinkAnnotation.super.prototype.onClick.apply( this, arguments );
	e.preventDefault();
	e.stopPropagation();
	mw.hook( 'growthExperiments.onAnnotationClicked' ).fire( this.model, this.isActive );
};

/**
 * @inheritdoc
 */
CERecommendedLinkAnnotation.prototype.attachContents = function () {
	CERecommendedLinkAnnotation.super.prototype.attachContents.apply( this, arguments );
	let shouldAnimateIcons = false;
	const newState = this.model.getState(),
		stateData = AnnotationAnimation.getLastState(),
		icons = [];

	// The following classes are used here:
	// * mw-ge-recommendedLinkAnnotation-icon-accepted
	// * mw-ge-recommendedLinkAnnotation-icon-rejected
	// * mw-ge-recommendedLinkAnnotation-icon-undecided
	const $toIcon = $( '<span>' ).addClass( 'mw-ge-recommendedLinkAnnotation-icon-' + newState );
	icons.push( $toIcon );

	let $fromIcon, isDeselect;
	if ( this.isRenderingUpdatedAnnotation() ) {
		const oldState = stateData.oldState;
		isDeselect = stateData.isDeselect;
		shouldAnimateIcons = oldState !== newState;
		// The following classes are used here:
		// * mw-ge-recommendedLinkAnnotation-icon-accepted
		// * mw-ge-recommendedLinkAnnotation-icon-rejected
		// * mw-ge-recommendedLinkAnnotation-icon-undecided
		$fromIcon = $( '<span>' ).addClass( 'mw-ge-recommendedLinkAnnotation-icon-' + oldState );
		// Show prior state (making it look as if the annotation view were permanent)
		$fromIcon.addClass( 'current' );
		$toIcon.addClass( isDeselect ? 'animate-from-top' : 'animate-from-bottom' );
		icons.push( $fromIcon );
	} else {
		$toIcon.addClass( 'current' );
	}

	const $icon = $( '<span>' ).addClass( 'mw-ge-recommendedLinkAnnotation-iconContainer' ).append( icons );
	this.$element.append( $icon );

	if ( shouldAnimateIcons ) {
		// Animate in the new icon shortly after the prior state is shown
		setTimeout( () => {
			$fromIcon.removeClass( 'current' ).addClass( isDeselect ? 'animate-from-bottom' : 'animate-from-top' );
			$toIcon.removeClass( 'animate-from-bottom animate-from-top' ).addClass( 'current' );
			AnnotationAnimation.clearLastState();
		}, 100 );
	}
};

/**
 * Check whether the annotation being rendered is the one for which the DataModel changed
 *
 * @return {boolean}
 */
CERecommendedLinkAnnotation.prototype.isRenderingUpdatedAnnotation = function () {
	return AnnotationAnimation.getLastState().recommendationWikitextOffset ===
		this.model.getAttribute( 'recommendationWikitextOffset' );
};

module.exports = CERecommendedLinkAnnotation;
