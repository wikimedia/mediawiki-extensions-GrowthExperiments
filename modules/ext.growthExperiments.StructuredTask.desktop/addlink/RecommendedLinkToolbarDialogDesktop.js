var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	RecommendedLinkToolbarDialog = StructuredTask.RecommendedLinkToolbarDialog,
	LinkSuggestionInteractionLogger = StructuredTask.LinkSuggestionInteractionLogger;

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogDesktop
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialogDesktop() {
	RecommendedLinkToolbarDialogDesktop.super.apply( this, arguments );
	this.$element.addClass( [ 'mw-ge-recommendedLinkToolbarDialog-desktop' ] );
	this.minHeight = 200;
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: false,
		active_interface: 'recommendedlinktoolbar_dialog'
		/* eslint-enable camelcase */
	} );
}

OO.inheritClass( RecommendedLinkToolbarDialogDesktop, RecommendedLinkToolbarDialog );

RecommendedLinkToolbarDialogDesktop.static.size = 'full';
RecommendedLinkToolbarDialogDesktop.static.position = 'inline';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.initialize = function () {
	RecommendedLinkToolbarDialogDesktop.super.prototype.initialize.call( this );
	this.$body.append( this.$buttons );
	this.$anchor = $( '<div>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-desktop-anchor' );
	this.$element.prepend( this.$anchor );
	this.$element.detach();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.afterSetupProcess = function () {
	RecommendedLinkToolbarDialogDesktop.super.prototype.afterSetupProcess.call( this );
	var ceSurface = this.surface.getView();
	ceSurface.$element.append( this.$element );
	// Prevent virtual keyboard from showing up when desktop site is loaded on tablet
	ceSurface.$documentNode.attr( 'inputMode', 'none' );
	// Handle Esc keydown even if the user clicks on the surface (otherwise onDialogKeyDown
	// only gets called when the dialog is focused)
	this.documentNodeKeydownHandler = function ( e ) {
		this.onDialogKeyDown( e );
	}.bind( this );
	ceSurface.$documentNode.on( 'keydown', this.documentNodeKeydownHandler );
	$( window ).on( 'resize',
		OO.ui.debounce( this.updatePosition.bind( this ), 250 )
	);
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.updateContentForCurrentRecommendation = function () {
	RecommendedLinkToolbarDialogDesktop.super.prototype.updateContentForCurrentRecommendation.call( this );
	this.updatePosition();
	this.updateActionButtonsMode();
};

/**
 * Update the coordinates of the current window based on the location of the annotation
 * The window is aligned to the left edge of the annotation if it fits in the surface,
 * if not, it is aligned to the right edge.
 */
RecommendedLinkToolbarDialogDesktop.prototype.updatePosition = function () {
	var $surfaceView = this.surface.getView().$element,
		surfaceOffset = $surfaceView.offset(),
		surfaceWidth = $surfaceView.width(),
		$annotationView = this.annotationView.$element,
		annotationOffset = $annotationView.offset(),
		annotationWidth = $annotationView.outerWidth(),
		elementWidth = Math.max( this.$element.width(), 400 ),
		newPosition = {
			top: annotationOffset.top - surfaceOffset.top + 30
		},
		isRtl = this.surface.getDir() === 'rtl',
		positionName = isRtl ? 'right' : 'left',
		isStartAnchored = true,
		startPosition;

	if ( isRtl ) {
		// Offset is the surface's right edge and the annotation's right edge.
		startPosition = surfaceOffset.left + surfaceWidth - ( annotationOffset.left + annotationWidth );
	} else {
		startPosition = annotationOffset.left - surfaceOffset.left;
	}

	// Check whether the window will overflow past the surface edge
	if ( startPosition + elementWidth > surfaceWidth ) {
		newPosition[ positionName ] = startPosition - elementWidth + annotationWidth;
		isStartAnchored = false;
	} else {
		newPosition[ positionName ] = startPosition;
	}

	this.$element.css( newPosition );
	this.$anchor.toggleClass( 'mw-ge-recommendedLinkToolbarDialog-desktop-anchor-start', isStartAnchored );
	this.$anchor.toggleClass( 'mw-ge-recommendedLinkToolbarDialog-desktop-anchor-end', !isStartAnchored );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.teardown = function () {
	var $documentNode = this.surface.getView().$documentNode;
	$documentNode.attr( 'inputMode', '' );
	$documentNode.off( 'keydown', this.documentNodeKeydownHandler );
	return RecommendedLinkToolbarDialogDesktop.super.prototype.teardown.apply( this, arguments );
};

/**
 * Fade out the link inspector
 *
 * @return {jQuery.Promise} Promise which resolves when the transition is complete
 */
RecommendedLinkToolbarDialogDesktop.prototype.fadeOut = function () {
	var promise = $.Deferred();
	this.$element.on( 'transitionend', promise.resolve );
	this.$element.addClass( 'fade-out' );
	return promise;
};

/**
 * Fade out the link inspector
 *
 * @return {jQuery.Promise} Promise which resolves when the transition is complete
 */
RecommendedLinkToolbarDialogDesktop.prototype.fadeIn = function () {
	var promise = $.Deferred();
	this.$element.on( 'transitionend', promise.resolve );
	this.$element.removeClass( 'fade-out' );
	return promise;
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.showFirstRecommendation = function () {
	var promise = $.Deferred();
	this.fadeOut();
	this.scrollToAnnotationView( this.getAnnotationViewAtIndex( 0 ) ).always( function () {
		this.showRecommendationAtIndex( 0 );
		this.fadeIn().then( promise.resolve );
	}.bind( this ) );
	return promise;
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.showRecommendationAtIndex = function (
	index, manualFocus
) {
	var updateContent = function () {
		RecommendedLinkToolbarDialogDesktop.super.prototype.showRecommendationAtIndex.call(
			this, index, manualFocus
		);
	}.bind( this );

	if ( this.isFirstRender ) {
		this.isFirstRender = false;
		updateContent();
		return;
	}

	if ( this.annotationView ) {
		this.annotationView.updateActiveClass( false );
	}

	this.fadeOut();
	// Call scrollToAnnotationView right away instead of wait for fadeOut to resolve
	// so that fade animation can be cancelled if scrolling isn't needed
	this.scrollToAnnotationView( this.getAnnotationViewAtIndex( index ) ).always( function () {
		updateContent();
		this.fadeIn();
	}.bind( this ) );
};

module.exports = RecommendedLinkToolbarDialogDesktop;
