const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	RecommendedLinkToolbarDialog = StructuredTask.addLink().RecommendedLinkToolbarDialog,
	LinkSuggestionInteractionLogger = StructuredTask.addLink().LinkSuggestionInteractionLogger,
	MinimizedToolbarDialogButton = require( '../MinimizedToolbarDialogButton.js' );

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogDesktop
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 *
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
	/** @property {mw.libs.ge.MinimizedToolbarDialogButton|null} toolbarDialogButton */
	this.toolbarDialogButton = null;
}

OO.inheritClass( RecommendedLinkToolbarDialogDesktop, RecommendedLinkToolbarDialog );

RecommendedLinkToolbarDialogDesktop.static.size = 'full';
RecommendedLinkToolbarDialogDesktop.static.position = 'inline';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.initialize = function () {
	RecommendedLinkToolbarDialogDesktop.super.prototype.initialize.call( this );
	this.$anchor = $( '<div>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-desktop-anchor' );
	this.$element.prepend( this.$anchor );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.afterSetupProcess = function () {
	RecommendedLinkToolbarDialogDesktop.super.prototype.afterSetupProcess.call( this );
	this.$body.append( this.$buttons );
	this.isRtl = this.surface.getDir() === 'rtl';
	this.setupMinification();
	this.moveDialogToSurfaceView();
	const ceSurface = this.surface.getView();
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
	RecommendedLinkToolbarDialogDesktop.super.prototype.updateContentForCurrentRecommendation
		.call( this );
	this.updatePosition();
	this.updateActionButtonsMode();
};

/**
 * Update the coordinates of the current window based on the location of the annotation
 * The window is aligned to the left edge of the annotation if it fits in the surface,
 * if not, it is aligned to the right edge.
 */
RecommendedLinkToolbarDialogDesktop.prototype.updatePosition = function () {
	const $surfaceView = this.surface.getView().$element,
		surfaceOffset = $surfaceView.offset(),
		surfaceWidth = $surfaceView.width(),
		$annotationView = this.annotationView.$element,
		annotationOffset = $annotationView.offset(),
		annotationWidth = $annotationView.outerWidth(),
		elementWidth = Math.max( this.$element.width(), 400 ),
		newPosition = {
			top: annotationOffset.top - surfaceOffset.top + 30
		},
		positionName = this.isRtl ? 'right' : 'left';
	let isStartAnchored = true;

	let startPosition;
	if ( this.isRtl ) {
		// Offset is the surface's right edge and the annotation's right edge.
		startPosition = surfaceOffset.left + surfaceWidth -
			( annotationOffset.left + annotationWidth );
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

	// Cache the viewport dimensions to be used during minification
	this.viewportWidth = window.innerWidth;
	this.viewportHeight = window.innerHeight;
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.teardown = function () {
	this.surface.getView().$documentNode
		.attr( 'inputMode', '' )
		.off( 'keydown', this.documentNodeKeydownHandler );
	return RecommendedLinkToolbarDialogDesktop.super.prototype.teardown.apply( this, arguments );
};

/**
 * Fade out the link inspector
 *
 * @return {jQuery.Promise} Promise which resolves when the transition is complete
 */
RecommendedLinkToolbarDialogDesktop.prototype.fadeOut = function () {
	const deferred = $.Deferred();
	this.$element.on( 'transitionend', deferred.resolve );
	this.$element.addClass( 'fade-out' );
	return deferred.promise();
};

/**
 * Fade out the link inspector
 *
 * @return {jQuery.Promise} Promise which resolves when the transition is complete
 */
RecommendedLinkToolbarDialogDesktop.prototype.fadeIn = function () {
	const deferred = $.Deferred();
	this.$element.on( 'transitionend', deferred.resolve );
	this.$element.removeClass( 'fade-out' );
	return deferred.promise();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialog.prototype.beforeShowFirstRecommendation = function () {
	this.fadeOut();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.afterShowFirstRecommendation = function () {
	return this.fadeIn();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogDesktop.prototype.showRecommendationAtIndex = function (
	index, manualFocus
) {
	const updateContent = function () {
		RecommendedLinkToolbarDialogDesktop.super.prototype.showRecommendationAtIndex.call(
			this, index, manualFocus
		);
	}.bind( this );

	if ( this.isFirstRender ) {
		this.isFirstRender = false;
		updateContent();
		return;
	}

	this.fadeOut();
	const isOpeningNewSelection = this.isHidden && manualFocus && this.currentIndex !== index;
	this.toggleDialogVisibility( true, isOpeningNewSelection );
	// Call scrollToAnnotationView right away instead of wait for fadeOut to resolve
	// so that fade animation can be cancelled if scrolling isn't needed
	this.scrollToAnnotationView( this.getAnnotationViewAtIndex( index ) ).always( () => {
		updateContent();
		this.fadeIn();
	} );
};

/**
 * Set up the close button and the button for re-opening the dialog
 */
RecommendedLinkToolbarDialogDesktop.prototype.setupMinification = function () {
	this.closeButton = new OO.ui.ButtonWidget( {
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-desktop-closeButton' ],
		framed: false,
		icon: 'close'
	} );
	this.closeButton.connect( this, { click: 'onCloseButtonClicked' } );
	this.$head.append( this.closeButton.$element );

	this.toolbarDialogButton = new MinimizedToolbarDialogButton( {
		label: mw.message( 'growthexperiments-addlink-context-button-show-suggestion' ).text()
	} );
	this.toolbarDialogButton.on( 'click', this.onToolbarDialogButtonClicked.bind( this ) );
	const minimizedButtonLocationDock = mw.util.addPortletLink( 'p-dock-bottom', '#', '' );
	const $minimizedButtonLocation = $( minimizedButtonLocationDock ).html( '' );
	this.toolbarDialogButton.$element.appendTo( $minimizedButtonLocation );
};

/**
 * Show or hide the dialog
 *
 * @param {boolean} isVisible Whether the dialog should be shown
 * @param {boolean} [disableTransition] Whether the transition animation should be disabled
 *
 *  When the dialog is closed, it animates into the robot button. When the robot button is clicked,
 *  the dialog animates back into the original state. While the dialog is closed, if the user clicks
 *  on a different suggestion, the animation no longer applies since the dialog will be opened in a
 *  different location.
 */
RecommendedLinkToolbarDialogDesktop.prototype.toggleDialogVisibility = function (
	isVisible,
	disableTransition
) {
	const $dialog = this.$element;
	let transformVal = 'none';

	$dialog.toggleClass(
		'mw-ge-recommendedLinkToolbarDialog-desktop--no-transition',
		!!disableTransition
	);
	if ( !isVisible ) {
		// Minimize the dialog into the robot button
		// This is done via a transformation that repositions the element and scales it down.
		// Transformation is used instead of changing the element position directly so that the
		// element can animate back to its original position when the dialog is re-opened.
		const boundingClientRect = $dialog.get( 0 ).getBoundingClientRect(),
			offset = 48, // account for the help panel button
			y = this.viewportHeight - boundingClientRect.bottom + offset;
		let x;
		if ( this.isRtl ) {
			x = -1 * ( boundingClientRect.left + offset );
		} else {
			x = this.viewportWidth - boundingClientRect.right + offset;
		}
		transformVal = 'translate(' + x + 'px, ' + y + 'px)  scale(0)';
	}
	$dialog.css( 'transform', transformVal );
	this.toolbarDialogButton.emit( 'dialogVisibilityChanged', isVisible );
	this.isHidden = !isVisible;
	this.annotationView.updateActiveClass( isVisible );
};

/**
 * Close the dialog
 */
RecommendedLinkToolbarDialogDesktop.prototype.onCloseButtonClicked = function () {
	this.toggleDialogVisibility( false );
	this.logger.log( 'close', this.getSuggestionLogActionData() );
};

/**
 * Scroll to the suggestion and re-open the dialog
 */
RecommendedLinkToolbarDialog.prototype.onToolbarDialogButtonClicked = function () {
	this.logger.log(
		'reopen_dialog_click',
		{},
		// eslint-disable-next-line camelcase
		{ active_interface: 'machinesuggestions_mode' }
	);
	this.scrollToAnnotationView( this.getAnnotationViewAtIndex( this.currentIndex ) ).then(
		() => {
			this.toggleDialogVisibility( true );
			this.logger.log( 'impression', this.getSuggestionLogActionData() );
		}
	);
};

module.exports = RecommendedLinkToolbarDialogDesktop;
