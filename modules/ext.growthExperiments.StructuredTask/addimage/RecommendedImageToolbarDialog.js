const StructuredTaskToolbarDialog = require( '../StructuredTaskToolbarDialog.js' ),
	MachineSuggestionsMode = require( '../MachineSuggestionsMode.js' ),
	ImageSuggestionInteractionLogger = require( './ImageSuggestionInteractionLogger.js' ),
	AddImageUtils = require( './AddImageUtils.js' ),
	router = require( 'mediawiki.router' ),
	COMPACT_VIEW_BREAKPOINT = 360,
	// Milliseconds before attempting the next UI change, used when navigating between states
	RENDER_DELAY = 300;

/**
 * @typedef {Object} mw.libs.ge.RecommendedImageMetadata
 *
 * @property {string} description HTML string of the image description from Commons
 * @property {string} descriptionUrl URL of the image description on Commons
 * @property {string} thumbUrl URL of the image thumbnail
 * @property {string} fullUrl URL of the original image
 * @property {number} originalWidth Width of the image (in px)
 * @property {number} originalHeight Height of the image (in px)
 * @property {boolean} mustRender Whether the image file needs to be re-rasterized
 * @property {boolean} isVectorized Whether the image file is a vector
 * @property {string} reason Text explaining why the image was suggested
 */

/**
 * @class mw.libs.ge.RecommendedImageToolbarDialog
 * @extends mw.libs.ge.StructuredTaskToolbarDialog
 * @constructor
 */
function RecommendedImageToolbarDialog() {
	RecommendedImageToolbarDialog.super.apply( this, arguments );
	this.$element.addClass( [
		'mw-ge-recommendedImageToolbarDialog',
		'animate-below',
		OO.ui.isMobile() ?
			'mw-ge-recommendedImageToolbarDialog-mobile' :
			'mw-ge-recommendedImageToolbarDialog-desktop',
	] );

	/**
	 * @property {jQuery} $buttons Container for Yes, No, Skip buttons
	 */
	this.$buttons = $( '<div>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-buttons' );

	/**
	 * @property {jQuery} $reason Suggestion reason
	 */
	this.$reason = $( '<div>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-reason' );

	/**
	 * @property {jQuery} $imagePreview Container for image thumbnail and description
	 */
	this.$imagePreview = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-imagePreview',
	);
	/**
	 * @property {OO.ui.ButtonWidget} yesButton
	 */
	this.yesButton = new OO.ui.ButtonWidget( {
		icon: 'check',
		label: mw.message( 'growthexperiments-addimage-inspector-yes-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-yes' ],
	} );
	this.yesButton.connect( this, { click: [ 'onYesButtonClicked' ] } );

	/**
	 * @property {OO.ui.ButtonWidget} noButton
	 */
	this.noButton = new OO.ui.ButtonWidget( {
		icon: 'close',
		label: mw.message( 'growthexperiments-addimage-inspector-no-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-no' ],
	} );
	this.noButton.connect( this, { click: [ 'onNoButtonClicked' ] } );

	/**
	 * @property {OO.ui.ButtonWidget} skipButton
	 */
	this.skipButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'growthexperiments-addimage-inspector-skip-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-skip' ],
	} );
	this.skipButton.connect( this, { click: [ 'onSkipButtonClicked' ] } );

	/**
	 * @property {OO.ui.ButtonWidget} detailsButton
	 */
	this.detailsButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'growthexperiments-addimage-inspector-details-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-details-button' ],
		icon: 'infoFilled',
		flags: [ 'progressive' ],
	} );
	this.detailsButton.connect( this, { click: [ 'onDetailsButtonClicked' ] } );

	/**
	 * @property {mw.libs.ge.ImageRecommendationImage[]} images
	 */
	this.images = [];
	/**
	 * @property {Function} onImageCaptionReady
	 */
	this.onImageCaptionReady = this.imageCaptionReadyHandler.bind( this );
	/**
	 * @property {mw.libs.ge.ImageSuggestionInteractionLogger} logger
	 */
	this.logger = new ImageSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'recommendedimagetoolbar_dialog',
		/* eslint-enable camelcase */
	} );
}

OO.inheritClass( RecommendedImageToolbarDialog, StructuredTaskToolbarDialog );

RecommendedImageToolbarDialog.static.name = 'recommendedImage';
RecommendedImageToolbarDialog.static.size = 'full';
RecommendedImageToolbarDialog.static.position = 'below';

/** @inheritDoc **/
RecommendedImageToolbarDialog.prototype.initialize = function () {
	RecommendedImageToolbarDialog.super.prototype.initialize.call( this );
	const $title = $( '<span>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-title' )
			.text(
				mw.message( 'growthexperiments-addimage-inspector-title' ).text(),
			),
		$cta = $( '<p>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-addImageCta' )
			.text( mw.message( 'growthexperiments-addimage-inspector-cta' ).text() );
	this.$head.append( [ this.getRobotIcon(), $title ] );
	this.setupButtons();
	this.$body.addClass( 'mw-ge-recommendedImageToolbarDialog-body' )
		.append( [ this.getBodyContent(), $cta, this.$buttons ] );
};

/** @inheritDoc **/
RecommendedImageToolbarDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return RecommendedImageToolbarDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.surface = data.surface;
			this.afterSetupProcess();
			// OOUI Window's updateSize is called when the OOUI Process is done executing.
			// A delay is returned from the step here to ensure that the content updated during
			// afterSetupProcess are in place before the dialog height is determined.
			return RENDER_DELAY;
		}, this );
};

/**
 * Initialize elements after this.surface is set
 */
RecommendedImageToolbarDialog.prototype.afterSetupProcess = function () {
	const articleTarget = this.getArticleTarget();

	this.images = articleTarget.images;
	this.setUpToolbarDialogButton(
		mw.message( 'growthexperiments-addimage-inspector-show-button' ).text(),
	);
	if ( OO.ui.isMobile() ) {
		MachineSuggestionsMode.disableVirtualKeyboard( this.surface );
		this.setupHelpButton(
			mw.message( 'growthexperiments-addimage-inspector-help-button' ).text(),
		);
		this.addArticleTitle();
		$( window ).on( 'resize',
			OO.ui.debounce( this.onResize.bind( this ), 250 ),
		);
	} else {
		this.moveDialogToSurfaceView();
	}
	this.showRecommendationAtIndex( 0 );
	if ( articleTarget.isSectionLevelTask() ) {
		/** @type {mw.libs.ge.AddSectionImageArticleTarget} */
		const sectionImageArticleTarget = articleTarget;
		sectionImageArticleTarget.insertImagePlaceholder( this.images[ 0 ] );
		sectionImageArticleTarget.scrollToTargetSection().then( () => {
			this.animateIn();
		} );
	} else {
		this.animateIn();
	}

	mw.hook( 'growthExperiments.imageSuggestions.onImageCaptionReady' ).add(
		this.onImageCaptionReady,
	);
};

/**
 * Resize the inspector if it's shown
 *
 * On some devices, the resize event is fired when the virtual keyboard appears (during caption).
 * Since the inspector is hidden, the new height would be 0.
 */
RecommendedImageToolbarDialog.prototype.onResize = function () {
	if ( this.isVisible() ) {
		this.updateSize();
	}
};

/**
 * Insert the image and start caption step
 */
RecommendedImageToolbarDialog.prototype.onYesButtonClicked = function () {
	if ( ve.init.target.isSectionLevelTask() ) {
		ve.init.target.replacePlaceholderWithImage( this.images[ 0 ] );
	} else {
		ve.init.target.insertImage( this.images[ 0 ] );
	}
	this.setState( true, [] );
	this.logger.log( 'suggestion_accept', this.getSuggestionLogActionData() );
	this.setUpCaptionStep();
};

/**
 * Show the rejection dialog
 */
RecommendedImageToolbarDialog.prototype.onNoButtonClicked = function () {
	const rejectionReasons = ve.init.target.recommendationRejectionReasons,
		rejectionDialogLifecycle = this.surface.dialogs.openWindow(
			'recommendedImageRejection', rejectionReasons ),
		// eslint-disable-next-line camelcase
		metadataOverride = { active_interface: 'rejection_dialog' },
		getLogActionData = function () {
			return Object.assign( this.getSuggestionLogActionData(), {
				// eslint-disable-next-line camelcase
				rejection_reasons: ve.init.target.recommendationRejectionReasons || [],
			} );
		}.bind( this );

	this.logger.log( 'suggestion_reject', this.getSuggestionLogActionData() );

	// Avoid showing an outline on the element if we cancel out of the rejection dialog.
	this.noButton.$element.find( '.oo-ui-buttonElement-button' ).trigger( 'blur' );

	rejectionDialogLifecycle.opening.then( () => {
		this.logger.log( 'impression', getLogActionData(), metadataOverride );
	} );

	rejectionDialogLifecycle.closed.then( ( data ) => {
		if ( data && data.action === 'done' ) {
			this.setState( false, data.reasons );
			this.getArticleTarget().saveWithoutShowingDialog();
			this.logger.log( 'confirm_reject_suggestion', getLogActionData(), metadataOverride );
		}
		this.logger.log( 'close', getLogActionData(), metadataOverride );
	} );
};

/**
 * Show a dialog confirming whether the user would like to leave without accepting or rejecting
 * the image suggestion
 */
RecommendedImageToolbarDialog.prototype.onSkipButtonClicked = function () {
	this.logger.log( 'suggestion_skip', this.getSuggestionLogActionData() );
	// eslint-disable-next-line camelcase
	const logMetadata = { active_interface: 'skip_dialog' },
		openSkipDialogPromise = this.surface.dialogs.openWindow( 'structuredTaskMessage', {
			title: mw.message( 'growthexperiments-addimage-skip-dialog-title' ).text(),
			message: mw.message( 'growthexperiments-addimage-skip-dialog-body' ).text(),
			actions: [
				{
					action: 'confirm',
					label: mw.message(
						'growthexperiments-addimage-skip-dialog-confirm',
					).text(),
				},
				{
					action: 'cancel',
					label: mw.message( 'growthexperiments-addimage-skip-dialog-cancel' ).text(),
				},
			],
		} );

	openSkipDialogPromise.opening.then( () => {
		this.logger.log( 'impression', this.getSuggestionLogActionData(), logMetadata );
	} );

	openSkipDialogPromise.closed.then( ( data ) => {
		const actionData = this.getSuggestionLogActionData();
		if ( data && data.action === 'confirm' ) {
			this.logger.log( 'confirm_skip_suggestion', actionData, logMetadata );
			this.goToSuggestedEdits();
		} else {
			this.logger.log( 'close', actionData, logMetadata );
			this.regainFocus();
		}
	} );
};

/**
 * Open the image viewer dialog
 */
RecommendedImageToolbarDialog.prototype.onFullscreenButtonClicked = function () {
	const imageData = this.images[ this.currentIndex ],
		surface = this.surface,
		// eslint-disable-next-line camelcase
		logMetadata = { active_interface: 'imageviewer_dialog' },
		actionData = this.getSuggestionLogActionData();

	const openImageViewerDialogPromise = surface.dialogs.openWindow(
		'recommendedImageViewer',
		imageData.metadata,
	);
	openImageViewerDialogPromise.opening.then( () => {
		this.logger.log( 'impression', actionData, logMetadata );
	} );

	openImageViewerDialogPromise.closed.then( () => {
		this.logger.log( 'close', actionData, logMetadata );
	} );

	this.showInternalRoute( 'imageviewer', () => {
		const currentWindow = surface.dialogs.currentWindow;
		if ( currentWindow ) {
			currentWindow.close();
		}
	} );
};

RecommendedImageToolbarDialog.prototype.onDetailsButtonClicked = function () {
	const surface = this.surface,
		imageData = this.images[ this.currentIndex ];
	surface.dialogs.openWindow( 'addImageDetails', {
		recommendation: imageData,
		logSource: 'toolbar_dialog',
		imageIndex: this.currentIndex,
	} );
};

/**
 * Add event lissteners for Yes and No buttons and append them to this.$buttons
 */
RecommendedImageToolbarDialog.prototype.setupButtons = function () {
	const $acceptanceButtons = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-buttons-acceptance-group',
	);
	$acceptanceButtons.append( [ this.yesButton.$element, this.noButton.$element ] );
	this.$buttons.append( [ $acceptanceButtons, this.skipButton.$element ] );
};

/**
 * Set up dialog content and return the container element
 *
 * @return {jQuery}
 */
RecommendedImageToolbarDialog.prototype.getBodyContent = function () {
	const $bodyContent = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-bodyContent',
	);
	this.setupImagePreview();
	$bodyContent.append( [ this.$reason, this.$imagePreview ] );
	return $bodyContent;
};

/**
 * Set up image thumbnail and image fullscreen button
 */
RecommendedImageToolbarDialog.prototype.setupImagePreview = function () {
	this.$imageThumbnail = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-image-thumbnail',
	).attr( 'role', 'button' );
	this.$imageInfo = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-image-info',
	);
	this.$imageThumbnail.append( new OO.ui.IconWidget( {
		icon: 'fullScreen',
		classes: [ 'mw-ge-recommendedImageToolbarDialog-fullScreen-icon' ],
	} ).$element );
	this.$imageThumbnail.on( 'click', this.onFullscreenButtonClicked.bind( this ) );
	this.$imagePreview.append( this.$imageThumbnail, this.$imageInfo );
};

/**
 * Show recommendation at the specified index
 *
 * @param {number} index Zero-based index of the recommendation in the images array
 */
RecommendedImageToolbarDialog.prototype.showRecommendationAtIndex = function ( index ) {
	this.currentIndex = index;
	this.updateSuggestionContent();
};

/**
 * Construct filename element
 *
 * @param {string} title Link title
 * @return {jQuery}
 */
RecommendedImageToolbarDialog.prototype.getFilenameElement = function ( title ) {
	return $( '<div>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-filename' )
		.text( title );
};

/**
 * Construct description element with text from the specified HTML string
 *
 * @param {string} descriptionHtml
 * @return {jQuery}
 */
RecommendedImageToolbarDialog.prototype.getDescriptionElement = function ( descriptionHtml ) {
	let descriptionText = $.parseHTML( descriptionHtml ).map( ( node ) => {
		if ( node.nodeType === Node.ELEMENT_NODE ) {
			return node.innerText;
		} else if ( node.nodeType === Node.TEXT_NODE ) {
			return node.textContent;
		} else {
			return '';
		}
	} ).join( '' );
	const hasDescription = descriptionText.length > 0;

	if ( !hasDescription ) {
		descriptionText = mw.message(
			'growthexperiments-addimage-inspector-description-placeholder',
		).text();
	}
	return $( '<p>' )
		.addClass( [
			'mw-ge-recommendedImageToolbarDialog-description',
			hasDescription ? '' : 'mw-ge-recommendedImageToolbarDialog-description--placeholder',
		] )
		.text( descriptionText );
};

/**
 * Update content specific to the current suggestion
 */
RecommendedImageToolbarDialog.prototype.updateSuggestionContent = function () {
	const imageData = this.images[ this.currentIndex ],
		metadata = imageData.metadata,
		thumbWidth = window.innerWidth > COMPACT_VIEW_BREAKPOINT ? 160 : 120,
		imageRenderData = AddImageUtils.getImageRenderData( metadata, window, thumbWidth );
	this.$reason.text( metadata.reason );
	this.$imageThumbnail
		.addClass( 'mw-no-invert' )
		.css( 'background-image', 'url("' + imageRenderData.src + '")' );
	// The description can be in a different language than the UI or content language, so the
	// text directionality should be set to auto instead of following the UI or the content.
	this.$imageInfo.append( [
		$( '<div>' ).attr( 'dir', 'auto' ).append( [
			this.getFilenameElement( imageData.displayFilename ),
			this.getDescriptionElement( metadata.description ),
		] ),
		this.detailsButton.$element,
	] );
};

/**
 * Change recommendation state (accepted/rejected).
 *
 * @param {boolean} accepted True for accepted, false for rejected.
 * @param {string[]} [reasons] List of reasons (RecommendedImageRejectionDialog option IDs
 *   such as 'no-info'), only when the recommendation was rejected.
 */
RecommendedImageToolbarDialog.prototype.setState = function ( accepted, reasons ) {
	this.getArticleTarget().updateSuggestionState( this.currentIndex, accepted, reasons );
	mw.hook( 'growthExperiments.suggestionAcceptanceChange' ).fire();
};

/**
 * @return {mw.libs.ge.AddImageArticleTarget}
 */
RecommendedImageToolbarDialog.prototype.getArticleTarget = function () {
	return ve.init.target;
};

/**
 * Update the editing surface and toolbar for caption step.
 * This gets called when the image preview is done loading.
 */
RecommendedImageToolbarDialog.prototype.imageCaptionReadyHandler = function () {
	if ( !this.canShowCaption ) {
		return;
	}
	const articleTarget = this.getArticleTarget(),
		surface = this.surface;
	MachineSuggestionsMode.enableVirtualKeyboard( surface, true );
	MachineSuggestionsMode.disableSurfacePaste( surface );
	surface.setReadOnly( false );
	// At this point, the rest of the surface (apart from the inserted image nodes, which have
	// contenteditable explicitly set to true) is not editable due to contenteditable being
	// false on the document node.
	articleTarget.toggleInternalRouting( true );
	articleTarget.updatePlaceholderTitle(
		mw.message( 'growthexperiments-addimage-caption-title' ).text(),
	);
	articleTarget.toggleEditModeTool( true );
	articleTarget.toggleSaveTool( true );
	// Hide the inspector after it's animated down to prevent it from showing up when adding caption
	this.toggle( false );
	articleTarget.logSuggestionInteraction( 'impression', 'caption_entry' );
};

/**
 * Set up loading states for the caption step & handler when returning from the step
 */
RecommendedImageToolbarDialog.prototype.setUpCaptionStep = function () {
	const articleTarget = this.getArticleTarget(),
		surface = this.surface,
		$inspector = this.$element,
		$documentNode = surface.getView().$documentNode;

	articleTarget.updatePlaceholderTitle(
		mw.message( 'growthexperiments-addimage-loading-title' ).text(),
		true,
	);
	// Loading states haven't been implemented on desktop. This might need to be updated once
	// we have desktop specs.
	if ( OO.ui.isMobile() ) {
		articleTarget.toggleEditModeTool( false );
		articleTarget.toggleSaveTool( false );
	}
	$documentNode.addClass( 'mw-ge-recommendedImageToolbarDialog-caption' );
	$inspector.addClass( 'animate-below' );
	this.canShowCaption = true;
	articleTarget.showCaptionInfoDialog( true );

	this.showInternalRoute( 'caption', () => {
		// The taskState is saved; the user pressed publish
		if ( ge.suggestedEditSession.taskState === 'saved' ) {
			return;
		}
		articleTarget.logSuggestionInteraction( 'back', 'caption_entry' );
		MachineSuggestionsMode.disableVirtualKeyboard( this.surface );
		MachineSuggestionsMode.enableSurfacePaste( this.surface );
		surface.setReadOnly( true );
		articleTarget.rollback();
		articleTarget.restorePlaceholderTitle();
		articleTarget.toggleInternalRouting( false );
		articleTarget.toggleEditModeTool( true );
		articleTarget.toggleSaveTool( false );
		$documentNode.removeClass( 'mw-ge-recommendedImageToolbarDialog-caption' );
		this.toggle( true );
		this.animateIn();
		this.canShowCaption = false;
	} );
};

/**
 * Show the specified step in the editing flow and allow the user to navigate back to prior step
 * using the browser's back mechanism.
 *
 * @param {string} routeName Name of the internal route to show
 * @param {Function} popstateHandler Handler to call when the user navigates back from the route
 */
RecommendedImageToolbarDialog.prototype.showInternalRoute = function (
	routeName, popstateHandler,
) {
	// On mobile, hashchange event with #/editor hash loads the editor. When opening the dialog,
	// add another history entry with the same hash so that going back (via browser) doesn't load
	// the editor again (since the hashchange event isn't triggered).
	const hash = OO.ui.isMobile() ? '#/editor/all' : '#' + routeName;
	router.navigateTo( routeName, {
		path: location.pathname + location.search + hash,
		useReplaceState: false,
	} );

	const onPopstate = function onPopstate() {
		popstateHandler();
		router.off( 'popstate', onPopstate );
	};
	router.on( 'popstate', onPopstate );
};

/**
 * Get metadata to pass to the ImageSuggestionInteractionLogger.
 *
 * @override
 * @return {Object}
 */
RecommendedImageToolbarDialog.prototype.getSuggestionLogActionData = function () {
	return this.getArticleTarget().getSuggestionLogActionData( this.currentIndex );
};

/**
 * Animate in the dialog and log an impression event
 */
RecommendedImageToolbarDialog.prototype.animateIn = function () {
	setTimeout( () => {
		this.$element.removeClass( 'animate-below' );
		this.logger.log( 'impression', this.getSuggestionLogActionData() );
	}, RENDER_DELAY );
};

module.exports = RecommendedImageToolbarDialog;
