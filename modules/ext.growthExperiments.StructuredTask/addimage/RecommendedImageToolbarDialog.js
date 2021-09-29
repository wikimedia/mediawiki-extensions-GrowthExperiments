var StructuredTaskToolbarDialog = require( '../StructuredTaskToolbarDialog.js' ),
	MachineSuggestionsMode = require( '../MachineSuggestionsMode.js' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

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
 * @extends  mw.libs.ge.StructuredTaskToolbarDialog
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
	 * @property {OO.ui.ToggleButtonWidget} yesButton
	 */
	this.yesButton = new OO.ui.ToggleButtonWidget( {
		icon: 'check',
		label: mw.message( 'growthexperiments-addimage-inspector-yes-button' ).text(),
		classes: [ 'mw-ge-recommendedImageToolbarDialog-buttons-yes' ]
	} );
	this.yesButton.connect( this, { click: [ 'onYesButtonClicked' ] } );

	/**
	 * @property {OO.ui.ToggleButtonWidget} noButton
	 */
	this.noButton = new OO.ui.ToggleButtonWidget( {
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
	 * @property {jQuery} $detailsButton
	 */
	this.$detailsButton = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-ge-recommendedImageToolbarDialog-details-button' )
		.text( mw.message( 'growthexperiments-addimage-inspector-details-button' ).text() );
	this.$detailsButton.on( 'click', this.onDetailsButtonClicked.bind( this ) );

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
};

RecommendedImageToolbarDialog.prototype.onYesButtonClicked = function () {
	// TODO: Caption (T290781)

	this.insertImage( this.images[ 0 ] );
	this.setState( true );
};

RecommendedImageToolbarDialog.prototype.onNoButtonClicked = function () {
	this.surface.dialogs.openWindow( 'recommendedImageRejection', this.rejectionReasons )
		.closed.then( function ( data ) {
			if ( data && data.action === 'done' ) {
				this.setState( false, data.reasons );
			}
		}.bind( this ) );
};

RecommendedImageToolbarDialog.prototype.onSkipButtonClicked = function () {
	// TODO: Skip functionality (T290910)
};

RecommendedImageToolbarDialog.prototype.onFullscreenButtonClicked = function () {
	var imageData = this.images[ this.currentIndex ];
	this.surface.dialogs.openWindow( 'recommendedImageViewer', imageData.metadata );
};

RecommendedImageToolbarDialog.prototype.onDetailsButtonClicked = function ( e ) {
	e.preventDefault();
	// TODO: Show image details dialog
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
	);
	this.$imageInfo = $( '<div>' ).addClass(
		'mw-ge-recommendedImageToolbarDialog-image-info'
	);
	this.fullscreenButton = new OO.ui.ButtonWidget( {
		icon: 'fullScreen',
		framed: false,
		classes: [ 'mw-ge-recommendedImageToolbarDialog-fullscreen-button' ]
	} );
	this.fullscreenButton.connect( this, { click: 'onFullscreenButtonClicked' } );
	this.$imageThumbnail.append( this.fullscreenButton.$element );
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
 * Construct link element
 *
 * @param {string} title Link title
 * @param {string} url Link target
 * @return {jQuery}
 */
RecommendedImageToolbarDialog.prototype.getFileLinkElement = function ( title, url ) {
	return $( '<a>' )
		.addClass( 'mw-ge-recommendedImageToolbarDialog-file-link' )
		.attr( { href: url, target: '_blank' } ).text( title );
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
	// TODO: format reason
	this.$reason.text( imageData.projects );
	this.$imageThumbnail.css( 'background-image', 'url(' + metadata.thumbUrl + ')' );
	this.$imageInfo.append( [
		$( '<div>' ).append( [
			this.getFileLinkElement( imageData.image, metadata.descriptionUrl ),
			this.getDescriptionElement( metadata.description )
		] ),
		$( '<div>' ).addClass( 'mw-ge-recommendedImageToolbarDialog-details-button-container' )
			.append( this.$detailsButton )
	] );
};

/**
 * Add the recommended image to the VE document.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 */
RecommendedImageToolbarDialog.prototype.insertImage = function ( imageData ) {
	var linearModel, insertOffset, dimensions,
		surfaceModel = this.surface.getModel(),
		data = surfaceModel.getDocument().data,
		NS_FILE = mw.config.get( 'wgNamespaceIds' ).file,
		imageTitle = new mw.Title( imageData.image, NS_FILE ),
		thumb = mw.util.parseImageUrl( imageData.metadata.thumbUrl ),
		// FIXME placeholder
		caption = imageTitle.getNameText();

	// Define the image to be inserted.
	// This will eventually be passed as the data parameter to MWBlockImageNode.toDomElements.
	// See also https://www.mediawiki.org/wiki/Specs/HTML/2.2.0#Images

	dimensions = ve.dm.MWImageNode.static.scaleToThumbnailSize( {
		width: imageData.metadata.originalWidth,
		height: imageData.metadata.originalHeight
	} );
	linearModel = [
		{
			type: 'mwBlockImage',
			attributes: {
				mediaClass: 'Image',
				// This is a Commons image so the link needs to use the English namespace name
				// but Title uses the localized one. That's OK, Parsoid will figure it out.
				// Native VE images also use localized titles.
				href: './' + imageTitle.getPrefixedText(),
				resource: './' + imageTitle.getPrefixedText(),
				type: 'thumb',
				defaultSize: true,
				// The generated wikitext will ignore width/height when defaultSize is set, but
				// it's still used for the visual size of the thumbnail in the editor, so set it
				// to something sensible.
				width: dimensions.width,
				height: dimensions.height,
				// Likewise only used in the editor UI. Work around an annoying quirk of MediaWiki
				// where a thumbnail with the exact same size as the original is not always valid.
				src: thumb.resizeUrl ?
					thumb.resizeUrl( Math.min( dimensions.width,
						imageData.metadata.originalWidth - 1 ) ) :
					imageData.metadata.thumbUrl,
				align: 'default',
				originalClasses: [ 'mw-default-size' ],
				isError: false,
				mw: {}
			},
			internal: {
				whitespace: [ '\n', undefined, undefined, '\n' ]
			}
		},
		{ type: 'mwImageCaption' },
		{ type: 'paragraph', internal: { generated: 'wrapper' } },
		// Caption will be spliced in here. In the linear model each character is a separate item.
		{ type: '/paragraph' },
		{ type: '/mwImageCaption' },
		{ type: '/mwBlockImage' }
	];
	Array.prototype.splice.apply( linearModel, [ 3, 0 ].concat( caption.split( '' ) ) );

	// Find the position between the initial templates and text.
	insertOffset = data.getRelativeOffset( 0, 1, function ( offset ) {
		return this.isEndOfMetadata( data, offset );
	}.bind( this ) );
	if ( insertOffset === -1 ) {
		// No valid position found. This shouldn't be possible.
		mw.log.error( 'No valid offset found for image insertion' );
		mw.errorLogger.logError( new Error( 'No valid offset found for image insertion' ),
			'error.growthexperiments' );
		insertOffset = 0;
	}

	// Actually insert the image.
	surfaceModel.setReadOnly( false );
	surfaceModel.getLinearFragment( new ve.Range( insertOffset ) ).insertContent( linearModel );
	surfaceModel.setReadOnly( true );
};

/**
 * Check whether a given offset in the linear model is the end of the leading metadata block
 * (in an editorial sense, not a technical sense).
 *
 * @param {ve.dm.ElementLinearData} data
 * @param {int} offset
 * @return {boolean}
 */
RecommendedImageToolbarDialog.prototype.isEndOfMetadata = function ( data, offset ) {
	if ( !data.isContentOffset( offset ) ) {
		return false;
	}
	// we know this exists, otherwise isContentOffset would fail
	var right = data.getData( offset );

	// Special-case newlines because we don't want to stop at newlines separating templates.
	if ( right === '\n' ) {
		return this.isEndOfMetadata( data, offset + 1 );
	}
	// plain text or annotated text
	if ( typeof right === 'string' || Array.isArray( right ) ) {
		return true;
	}
	// right is an object. Skip it if it's a template or invisible metadata.
	if ( [
		// templates
		'mwTransclusion', 'mwTransclusionBlock', 'mwTransclusionInline', 'mwTransclusionTableCell',
		// ve.dm.MetaItem subclasses
		'mwAlienMeta', 'mwCategory', 'mwDefaultSort', 'mwDisplayTitle', 'mwHiddenCategory',
		'mwIndex', 'mwLanguage', 'mwNewSectionEdit', 'mwNoContentConvert', 'mwNoEditSection',
		'mwNoGallery', 'mwNoTitleConvert', 'mwDisambiguation',
		// hidden, so probably should go before the image
		'comment', 'mwLanguageVariantHidden'
	].indexOf( right.type ) !== -1 ) {
		return false;
	}
	return true;
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
	this.surface.geRecommendationAccepted = accepted;
	this.surface.geRecommendationRejectionReasons = reasons;
	this.surface.executeCommand( 'showSave' );
};

module.exports = RecommendedImageToolbarDialog;
