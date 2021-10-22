var StructuredTaskToolbarDialog = require( '../StructuredTaskToolbarDialog.js' ),
	MachineSuggestionsMode = require( '../MachineSuggestionsMode.js' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
	router = require( 'mediawiki.router' );

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
		OO.ui.isMobile() ?
			'mw-ge-recommendedImageToolbarDialog-mobile' :
			'mw-ge-recommendedImageToolbarDialog-desktop'
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
		'mw-ge-recommendedImageToolbarDialog-imagePreview'
	);
	/**
	 * @property {OO.ui.ButtonWidget} yesButton
	 */
	this.yesButton = new OO.ui.ButtonWidget( {
		icon: 'check',
		label: mw.message( 'growthexperiments-addimage-inspector-yes-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-yes' ]
	} );
	this.yesButton.connect( this, { click: [ 'onYesButtonClicked' ] } );

	/**
	 * @property {OO.ui.ButtonWidget} noButton
	 */
	this.noButton = new OO.ui.ButtonWidget( {
		icon: 'close',
		label: mw.message( 'growthexperiments-addimage-inspector-no-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-no' ]
	} );
	this.noButton.connect( this, { click: [ 'onNoButtonClicked' ] } );

	/**
	 * @property {OO.ui.ButtonWidget} skipButton
	 */
	this.skipButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'growthexperiments-addimage-inspector-skip-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-skip' ]
	} );
	this.skipButton.connect( this, { click: [ 'onSkipButtonClicked' ] } );

	/**
	 * @property {OO.ui.ButtonWidget} detailsButton
	 */
	this.detailsButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'growthexperiments-addimage-inspector-details-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-details-button' ],
		icon: 'info-filled'
	} );
	this.detailsButton.connect( this, { click: [ 'onDetailsButtonClicked' ] } );

	/**
	 * @property {Function} onDocumentNodeClick
	 */
	this.onDocumentNodeClick = this.hideDialog.bind( this );

	/**
	 * @property {mw.libs.ge.ImageRecommendationImage[]} images
	 */
	this.images = suggestedEditSession.taskData.images;
}

OO.inheritClass( RecommendedImageToolbarDialog, StructuredTaskToolbarDialog );

RecommendedImageToolbarDialog.static.name = 'recommendedImage';
RecommendedImageToolbarDialog.static.size = 'full';
RecommendedImageToolbarDialog.static.position = 'below';

/** @inheritDoc **/
RecommendedImageToolbarDialog.prototype.initialize = function () {
	RecommendedImageToolbarDialog.super.prototype.initialize.call( this );
	var $title = $( '<span>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-title' )
			.text(
				mw.message(
					'growthexperiments-addimage-inspector-title',
					[ mw.language.convertNumber( 1 ) ]
				).text()
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
		}, this );
};

/**
 * Initialize elements after this.surface is set
 */
RecommendedImageToolbarDialog.prototype.afterSetupProcess = function () {
	MachineSuggestionsMode.disableVirtualKeyboard( this.surface );
	this.surface.getView().$documentNode.on( 'click', this.onDocumentNodeClick );
	this.setUpToolbarDialogButton(
		mw.message( 'growthexperiments-addimage-inspector-show-button' ).text()
	);
	// TBD: Desktop UI (help panel CTA button is shown by default)
	if ( OO.ui.isMobile() ) {
		this.setupHelpButton(
			mw.message( 'growthexperiments-addimage-inspector-help-button' ).text()
		);
	}
	this.showRecommendationAtIndex( 0 );
	$( window ).on( 'resize',
		OO.ui.debounce( this.updateSize.bind( this ), 250 )
	);
};

RecommendedImageToolbarDialog.prototype.onYesButtonClicked = function () {
	// TODO: Caption (T290781)
	ve.init.target.insertImage( this.images[ 0 ] );
	this.setState( true );
	this.setUpCaptionStep();
};

RecommendedImageToolbarDialog.prototype.onNoButtonClicked = function () {
	this.surface.dialogs.openWindow( 'recommendedImageRejection', this.rejectionReasons )
		.closed.then( function ( data ) {
			if ( data && data.action === 'done' ) {
				this.setState( false, data.reasons );
			}
			mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
		}.bind( this ) );
};

/**
 * Show a dialog confirming whether the user would like to leave without accepting or rejecting
 * the image suggestion
 */
RecommendedImageToolbarDialog.prototype.onSkipButtonClicked = function () {
	this.surface.dialogs.openWindow( 'structuredTaskMessage', {
		title: mw.message( 'growthexperiments-addimage-skip-dialog-title' ).text(),
		message: mw.message( 'growthexperiments-addimage-skip-dialog-body' ).text(),
		actions: [
			{
				action: 'confirm',
				label: mw.message(
					'growthexperiments-addimage-skip-dialog-confirm'
				).text()
			},
			{
				action: 'cancel',
				label: mw.message( 'growthexperiments-addimage-skip-dialog-cancel' ).text()
			}
		]
	} ).closed.then( function ( data ) {
		if ( data && data.action === 'confirm' ) {
			this.endSession();
		} else {
			this.regainFocus();
		}
	}.bind( this ) );
};

/**
 * Open the image viewer dialog
 */
RecommendedImageToolbarDialog.prototype.onFullscreenButtonClicked = function () {
	var imageData = this.images[ this.currentIndex ],
		surface = this.surface;

	surface.dialogs.openWindow( 'recommendedImageViewer', imageData.metadata );
	this.showInternalRoute( 'imageviewer', function () {
		var currentWindow = surface.dialogs.currentWindow;
		if ( currentWindow ) {
			currentWindow.close();
		}
	} );
};

RecommendedImageToolbarDialog.prototype.onDetailsButtonClicked = function () {
	// TODO: Show image details dialog (T290782)
};

/**
 * Add event lissteners for Yes and No buttons and append them to this.$buttons
 */
RecommendedImageToolbarDialog.prototype.setupButtons = function () {
	var $acceptanceButtons = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-buttons-acceptance-group'
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
	var $bodyContent = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-bodyContent'
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
		'mw-ge-recommendedImageToolbarDialog-image-thumbnail'
	).attr( 'role', 'button' );
	this.$imageInfo = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-image-info'
	);
	this.$imageThumbnail.append( new OO.ui.IconWidget( {
		icon: 'fullScreen',
		classes: [ 'mw-ge-recommendedImageToolbarDialog-fullScreen-icon' ]
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
	// TODO: Filter out complicated content in description (infoboxes, tables etc)
	return $( '<p>' )
		.addClass( 'mw-ge-recommendedImageToolbarDialog-description' )
		.html( $.parseHTML( descriptionHtml ) );
};

/**
 * Update content specific to the current suggestion
 */
RecommendedImageToolbarDialog.prototype.updateSuggestionContent = function () {
	var imageData = this.images[ this.currentIndex ],
		metadata = imageData.metadata;
	// TODO: format reason (T292467)
	this.$reason.text( imageData.projects );
	this.$imageThumbnail.css( 'background-image', 'url(' + metadata.thumbUrl + ')' );
	this.$imageInfo.append( [
		$( '<div>' ).append( [
			this.getFilenameElement( imageData.image ),
			this.getDescriptionElement( metadata.description )
		] ),
		this.detailsButton.$element
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
	// FIXME this isn't the final behavior but useful now for testing.
	ve.init.target.recommendationAccepted = accepted;
	ve.init.target.recommendationRejectionReasons = reasons;
};

/**
 * @return {mw.libs.ge.AddImageArticleTarget}
 */
RecommendedImageToolbarDialog.prototype.getArticleTarget = function () {
	return ve.init.target;
};

/**
 * Hide the inspector, update surface & toolbar states for caption step
 */
RecommendedImageToolbarDialog.prototype.setUpCaptionStep = function () {
	var articleTarget = this.getArticleTarget(),
		surface = this.surface,
		toolbarDialogButton = this.toolbarDialogButton,
		$inspector = this.$element,
		$documentNode = surface.getView().$documentNode;

	MachineSuggestionsMode.enableVirtualKeyboard( surface, true );
	surface.setReadOnly( false );
	// At this point, the rest of the surface (apart from the inserted image nodes, which have
	// contenteditable explicitly set to true) is not editable due to contenteditable being false
	// on the document node.
	toolbarDialogButton.toggle( false );
	articleTarget.updatePlaceholderTitle(
		mw.message( 'growthexperiments-addimage-caption-title' ).escaped()
	);
	articleTarget.toggleInternalRouting( true );
	$documentNode.off( 'click', this.onDocumentNodeClick );
	$documentNode.addClass( 'mw-ge-recommendedImageToolbarDialog-caption' );
	$inspector.addClass( 'animate-below' );

	this.showInternalRoute( 'caption', function () {
		MachineSuggestionsMode.disableVirtualKeyboard( this.surface );
		surface.setReadOnly( true );
		toolbarDialogButton.toggle( true );
		articleTarget.rollback();
		articleTarget.restorePlaceholderTitle();
		articleTarget.toggleInternalRouting( false );
		$documentNode.on( 'click', this.onDocumentNodeClick );
		$documentNode.removeClass( 'mw-ge-recommendedImageToolbarDialog-caption' );
		$inspector.removeClass( 'animate-below' );
	}.bind( this ) );
};

/**
 * Show the specified step in the editing flow and allow the user to navigate back to prior step
 * using the browser's back mechanism.
 *
 * @param {string} routeName Name of the internal route to show
 * @param {Function} popstateHandler Handler to call when the user navigates back from the route
 */
RecommendedImageToolbarDialog.prototype.showInternalRoute = function (
	routeName, popstateHandler
) {
	// On mobile, hashchange event with #/editor hash loads the editor. When opening the dialog,
	// add another history entry with the same hash so that going back (via browser) doesn't load
	// the editor again (since the hashchange event isn't triggered).
	var hash = OO.ui.isMobile() ? '#/editor/all' : '#' + routeName;
	router.navigateTo( routeName, {
		path: location.pathname + location.search + hash,
		useReplaceState: false
	} );

	var onPopstate = function onPopstate() {
		popstateHandler();
		router.off( 'popstate', onPopstate );
	};
	router.on( 'popstate', onPopstate );
};

module.exports = RecommendedImageToolbarDialog;
