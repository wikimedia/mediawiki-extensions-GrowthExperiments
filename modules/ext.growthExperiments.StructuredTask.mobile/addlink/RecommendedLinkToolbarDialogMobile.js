const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	RecommendedLinkToolbarDialog = StructuredTask.addLink().RecommendedLinkToolbarDialog,
	LinkSuggestionInteractionLogger = StructuredTask.addLink().LinkSuggestionInteractionLogger,
	MachineSuggestionsMode = StructuredTask.MachineSuggestionsMode,
	SwipePane = require( '../../ui-components/SwipePane.js' );

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogMobile
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialogMobile() {
	RecommendedLinkToolbarDialogMobile.super.apply( this, arguments );
	this.$element.addClass( [ 'mw-ge-recommendedLinkToolbarDialog-mobile', 'animate-below' ] );
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: true,
		active_interface: 'recommendedlinktoolbar_dialog',
		/* eslint-enable camelcase */
	} );
	this.onDocumentNodeClick = this.hideDialog.bind( this );
}

OO.inheritClass( RecommendedLinkToolbarDialogMobile, RecommendedLinkToolbarDialog );

RecommendedLinkToolbarDialogMobile.static.size = 'full';
RecommendedLinkToolbarDialogMobile.static.position = 'below';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.initialize = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.initialize.call( this );
	this.$labelPreview = $( '<div>' ).addClass(
		'mw-ge-recommendedLinkToolbarDialog-labelPreview',
	);
	this.setupLabelPreview();
	this.$foot.append( this.$buttons );
	this.setupSwipeNavigation();
	this.setupHelpButton(
		mw.message( 'growthexperiments-addlink-context-button-help' ).text(),
	);
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.afterSetupProcess = function () {
	this.$body.prepend( this.$labelPreview );
	MachineSuggestionsMode.disableVirtualKeyboard( this.surface );
	this.surface.getView().$documentNode.on( 'click', this.onDocumentNodeClick );
	mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add( () => {
		// If onboarding is completed after selecting first recommendation, the selection needs to
		// be scrolled into view since it wasn't in the viewport when onboarding was open.
		this.surface.scrollSelectionIntoView();
	} );
	this.setUpToolbarDialogButton(
		mw.message( 'growthexperiments-addlink-context-button-show-suggestion' ).text(),
	);
	RecommendedLinkToolbarDialogMobile.super.prototype.afterSetupProcess.call( this );
	if ( this.linkRecommendationFragments.length > 1 ) {
		this.$acceptanceButtonsContainer = this.setUpAnimationContainer(
			this.$acceptanceButtonGroup, this.$buttons,
		);
	}
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.showRecommendationAtIndex = function (
	index, manualFocus ) {

	if ( this.isHidden ) {
		this.showDialog();
	}

	if ( this.isFirstRender ) {
		RecommendedLinkToolbarDialogMobile.super.prototype.showRecommendationAtIndex.call(
			this, index, manualFocus,
		);
		this.isFirstRender = false;
		return;
	}
	this.scrollToAnnotationView( this.getAnnotationViewAtIndex( index ) ).always( () => {
		RecommendedLinkToolbarDialogMobile.super.prototype.showRecommendationAtIndex.call(
			this, index, manualFocus,
		);
	} );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.updateContentForCurrentRecommendation = function () {
	const shouldAnimateContent = !this.isFirstRender;
	if ( shouldAnimateContent ) {
		this.prepareAnimatedContent( this.$labelPreviewTextContainer );
		this.prepareAnimatedContent( this.$linkPreviewContainer );
		this.prepareAnimatedContent( this.$acceptanceButtonsContainer, true );
	}

	RecommendedLinkToolbarDialogMobile.super.prototype.updateContentForCurrentRecommendation
		.call( this );

	if ( this.annotationView ) {
		this.$labelPreviewText.text( this.annotationView.$element.text() );
	}

	if ( shouldAnimateContent ) {
		this.isAnimating = true;
		// Delay animation to account for ToggleButtonWidget's transition
		setTimeout( () => {
			this.animateNewContent( this.$labelPreviewTextContainer );
			this.animateNewContent( this.$linkPreviewContainer );
			this.animateNewContent( this.$acceptanceButtonsContainer ).then( () => {
				this.isAnimating = false;
				this.updateActionButtonsMode();
			} );
		}, 150 );
	}
};

/**
 * Set up the template in which to show the text in the article the recommendation is for
 *
 * @private
 */
RecommendedLinkToolbarDialogMobile.prototype.setupLabelPreview = function () {
	this.$labelPreviewText = $( '<div>' ).addClass(
		'mw-ge-recommendedLinkToolbarDialog-labelPreview-text',
	);
	this.$labelPreviewTextContainer = this.setUpAnimationContainer( this.$labelPreviewText );
	this.$labelPreview.append( [
		this.$labelPreviewTextContainer,
	] );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.setupLinkPreview = function () {
	const $linkPreview = RecommendedLinkToolbarDialogMobile.super.prototype.setupLinkPreview.apply(
		this, arguments,
	);
	this.$linkPreviewContainer = this.setUpAnimationContainer( $linkPreview );
	return this.$linkPreviewContainer;
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.teardown = function () {
	MachineSuggestionsMode.enableVirtualKeyboard( this.surface );
	this.surface.getView().$documentNode.off( 'click', this.onDocumentNodeClick );
	return RecommendedLinkToolbarDialogMobile.super.prototype.teardown.apply( this, arguments );
};

/**
 * Set up container to animate the specified element in and out
 *
 * @param {jQuery} $el Element to animate in and out
 * @param {jQuery} [$existingContainer] Container element
 * @return {jQuery}
 */
RecommendedLinkToolbarDialogMobile.prototype.setUpAnimationContainer = function (
	$el, $existingContainer,
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
	$container, isContentAbsolutelyPositioned,
) {
	const $realCurrent = $container.find( '.current' ),
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
 * @return {jQuery.promise} Promise which resolves when the animation is done
 */
RecommendedLinkToolbarDialogMobile.prototype.animateNewContent = function ( $container ) {
	// Animate in new content
	const $fakeCurrent = $container.find( '.fake-current' ),
		$realCurrent = $container.find( '.current' ),
		deferred = $.Deferred();

	$container.addClass( 'animating' );
	$realCurrent.on( 'transitionend', function removeFakeCurrent() {
		$fakeCurrent.remove();
		$container.removeClass( [ 'animating', 'ready-to-animate' ] );
		$realCurrent.off( 'transitionend', removeFakeCurrent );
		deferred.resolve();
	} );
	$fakeCurrent.addClass( this.isGoingBack ? 'animate-from-end' : 'animate-from-start' );
	$realCurrent.removeClass( [ 'animate-from-end', 'animate-from-start' ] );
	return deferred.promise();
};

/**
 * Enable navigation via swipe gestures
 */
RecommendedLinkToolbarDialogMobile.prototype.setupSwipeNavigation = function () {
	const swipePane = new SwipePane( this.$body, {
		isRtl: document.documentElement.dir === 'rtl',
		isHorizontal: true,
	} );
	swipePane.setToStartHandler( () => {
		this.onNextButtonClicked( true );
	} );
	swipePane.setToEndHandler( () => {
		this.onPrevButtonClicked( true );
	} );
};

/** @inheritDoc **/
RecommendedLinkToolbarDialogMobile.prototype.showDialog = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.showDialog.apply( this, arguments );
	this.logger.log( 'close', this.getSuggestionLogActionData() );
};

/** @inheritDoc **/
RecommendedLinkToolbarDialogMobile.prototype.hideDialog = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.hideDialog.apply( this, arguments );
	this.logger.log( 'impression', this.getSuggestionLogActionData() );
};

module.exports = RecommendedLinkToolbarDialogMobile;
