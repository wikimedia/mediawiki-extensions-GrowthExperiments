var AddLink = require( 'ext.growthExperiments.AddLink' ),
	RecommendedLinkToolbarDialog = AddLink.RecommendedLinkToolbarDialog,
	LinkSuggestionInteractionLogger = AddLink.LinkSuggestionInteractionLogger;

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogMobile
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialogMobile() {
	RecommendedLinkToolbarDialogMobile.super.apply( this, arguments );
	this.$element.addClass( [ 'mw-ge-recommendedLinkToolbarDialog-mobile', 'animate-from-below' ] );
	this.topOffset = 25;
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: false,
		active_interface: 'recommendedlinktoolbar_dialog'
		/* eslint-enable camelcase */
	} );
}

OO.inheritClass( RecommendedLinkToolbarDialogMobile, RecommendedLinkToolbarDialog );

RecommendedLinkToolbarDialogMobile.static.size = 'full';
RecommendedLinkToolbarDialogMobile.static.position = 'below';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.initialize = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.initialize.call( this );
	this.$labelPreview = $( '<div>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-labelPreview' );
	this.setupLabelPreview();
	this.$body.prepend( this.$labelPreview );
	this.setupHelpButton();
	this.$acceptanceButtonsContainer = this.setUpAnimationContainer(
		this.$acceptanceButtonGroup, this.$buttons
	);
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.afterSetupProcess = function () {
	var ceSurface = this.surface.getView();
	// HACK: Disable virtual keyboard, text edit menu on the surface
	ceSurface.$documentNode.attr( 'contenteditable', false );
	ceSurface.$documentNode.addClass( 'mw-ge-user-select-none' );
	mw.hook( 'growthExperiments.addLinkOnboardingCompleted' ).add( function () {
		// If onboarding is completed after selecting first recommendation, the selection needs to
		// be scrolled into view since it wasn't in the viewport when onboarding was open.
		this.surface.scrollSelectionIntoView();
	}.bind( this ) );
	RecommendedLinkToolbarDialogMobile.super.prototype.afterSetupProcess.call( this );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.showRecommendationAtIndex = function (
	index, manualFocus ) {
	if ( this.isFirstRender ) {
		RecommendedLinkToolbarDialogMobile.super.prototype.showRecommendationAtIndex.call(
			this, index, manualFocus
		);
		this.isFirstRender = false;
		return;
	}
	this.scrollToAnnotationView( this.getAnnotationViewAtIndex( index ) ).always( function () {
		RecommendedLinkToolbarDialogMobile.super.prototype.showRecommendationAtIndex.call(
			this, index, manualFocus
		);
	}.bind( this ) );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.updateContentForCurrentRecommendation = function () {
	var shouldAnimateContent = !this.isFirstRender;
	if ( shouldAnimateContent ) {
		this.prepareAnimatedContent( this.$labelPreviewTextContainer );
		this.prepareAnimatedContent( this.$linkPreviewContainer );
		this.prepareAnimatedContent( this.$acceptanceButtonsContainer, true );
	}

	RecommendedLinkToolbarDialogMobile.super.prototype.updateContentForCurrentRecommendation.call( this );

	if ( this.annotationView ) {
		this.$labelPreviewText.text( this.annotationView.$element.text() );
	}

	if ( shouldAnimateContent ) {
		// Delay animation to account for ToggleButtonWidget's transition
		setTimeout( function () {
			this.animateNewContent( this.$labelPreviewTextContainer );
			this.animateNewContent( this.$linkPreviewContainer );
			this.animateNewContent( this.$acceptanceButtonsContainer );
			this.updateActionButtonsMode();
		}.bind( this ), 150 );
	}
};

/**
 * Show the next recommendation or save the article (if the last recommendation is shown)
 */
RecommendedLinkToolbarDialogMobile.prototype.onAcceptanceChanged = function () {
	var isLastRecommendationSelected = this.isLastRecommendationSelected();
	RecommendedLinkToolbarDialogMobile.super.prototype.onAcceptanceChanged.call( this );
	if ( this.shouldSkipAutoAdvance ) {
		return;
	}
	// Auto-advance after animation for the current recommendation is done
	// TODO: Animation delay as a config in AnnotationAnimation
	// Probably make sense to update this along with T283548 when auto-advance is enabled for desktop
	setTimeout( function () {
		if ( isLastRecommendationSelected ) {
			mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
		} else if ( !isLastRecommendationSelected ) {
			this.showRecommendationAtIndex( this.currentIndex + 1 );
		}
	}.bind( this ), 600 );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.selectAnnotationView = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.selectAnnotationView.call( this );
	this.surface.scrollSelectionIntoView();
};

/**
 * Set up the template in which to show the text in the article the recommendation is for
 *
 * @private
 */
RecommendedLinkToolbarDialogMobile.prototype.setupLabelPreview = function () {
	this.$labelPreviewText = $( '<div>' ).addClass(
		'mw-ge-recommendedLinkToolbarDialog-labelPreview-text'
	);
	this.$labelPreviewTextContainer = this.setUpAnimationContainer( this.$labelPreviewText );
	this.$labelPreview.append( [
		$( '<div>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-labelPreview-label' ).text(
			mw.message( 'growthexperiments-addlink-context-text-label' ).text()
		),
		this.$labelPreviewTextContainer
	] );
};

/**
 * Set up button that opens help panel
 *
 * @private
 */
RecommendedLinkToolbarDialogMobile.prototype.setupHelpButton = function () {
	var helpButton = new OO.ui.ButtonWidget( {
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-help-button' ],
		framed: false,
		icon: 'helpNotice',
		label: mw.message( 'growthexperiments-addlink-context-button-help' ).text(),
		invisibleLabel: true
	} );
	helpButton.on( 'click', function () {
		mw.hook( 'growthExperiments.contextItem.openHelpPanel' ).fire();
	} );
	this.$head.append( helpButton.$element );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.setupLinkPreview = function () {
	var $linkPreview = RecommendedLinkToolbarDialogMobile.super.prototype.setupLinkPreview.apply(
		this, arguments
	);
	this.$linkPreviewContainer = this.setUpAnimationContainer( $linkPreview );
	return this.$linkPreviewContainer;
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.teardown = function () {
	var ceSurface = this.surface.getView();
	ceSurface.$documentNode.attr( 'contenteditable', true );
	ceSurface.$documentNode.removeClass( 'mw-ge-user-select-none' );
	return RecommendedLinkToolbarDialogMobile.super.prototype.teardown.apply( this, arguments );
};

// Animation helpers

/**
 * Set up container to animate the specified element in and out
 *
 * @param {jQuery} $el Element to animate in and out
 * @param {jQuery} [$existingContainer] Container element
 * @return {jQuery}
 */
RecommendedLinkToolbarDialogMobile.prototype.setUpAnimationContainer = function (
	$el, $existingContainer
) {
	$el.addClass( [ 'current', 'animation-content' ] );
	if ( $existingContainer ) {
		$el.addClass( 'animation-content-with-position' );
		return $existingContainer.addClass( 'animation-container' );
	}
	return $( '<div>' ).addClass( 'animation-container' ).append( $el );
};

/**
 * Set up the specified animation container by cloning the current content that needs to be
 * animated out and position the upcoming content off-screen so that it can be animated in
 *
 * If the content being animated is not absolutely positioned, position it absolutely so that
 * the position can be animated.
 *
 * @param {jQuery} $container Animation container
 * @param {boolean} [isContentAbsolutelyPositioned] Whether the element being animated
 * is already absolutely positioned
 */
RecommendedLinkToolbarDialogMobile.prototype.prepareAnimatedContent = function (
	$container, isContentAbsolutelyPositioned
) {
	var $realCurrent = $container.find( '.current' ),
		$fakeCurrent = $realCurrent.clone().addClass( 'fake-current' ).removeClass( 'current' );
	// Explicitly set container height to hide content that's animating in
	if ( !isContentAbsolutelyPositioned ) {
		$container.css( 'height', $realCurrent.outerHeight() );
	}
	$realCurrent.addClass( this.isGoingBack ? 'animate-from-start' : 'animate-from-end' );
	$container.prepend( $fakeCurrent );
	// At this point, animation container should be showing a copy of the current view and the
	// upcoming view is positioned off-screen.
	$container.addClass( 'ready-to-animate' );
};

/**
 * Animate in upcoming content for the specified animation container
 *
 * @param {jQuery} $container Animation container
 */
RecommendedLinkToolbarDialogMobile.prototype.animateNewContent = function ( $container ) {
	// Animate in new content
	var $fakeCurrent = $container.find( '.fake-current' ),
		$realCurrent = $container.find( '.current' );

	$container.addClass( 'animating' );
	$realCurrent.on( 'transitionend', function removeFakeCurrent() {
		$fakeCurrent.remove();
		$container.removeClass( [ 'animating', 'ready-to-animate' ] );
		$realCurrent.off( 'transitionend', removeFakeCurrent );
	} );
	$fakeCurrent.addClass( this.isGoingBack ? 'animate-from-end' : 'animate-from-start' );
	$realCurrent.removeClass( [ 'animate-from-end', 'animate-from-start' ] );
};

module.exports = RecommendedLinkToolbarDialogMobile;
