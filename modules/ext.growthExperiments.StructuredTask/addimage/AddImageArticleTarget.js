var StructuredTaskPreEdit = require( 'ext.growthExperiments.StructuredTask.PreEdit' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

/**
 * @typedef mw.libs.ge.ImageRecommendationImage
 * @property {string} image Image filename in unprefixed DBkey format.
 * @property {string} source Recommendation source; one of 'wikidata', 'wikipedia', 'commons'.
 * @property {string[]} projects Lists of projects (as wiki IDs) the recommendation is from.
 *   Only used when the source is 'wikipedia'.
 * @property {Object} metadata
 * @property {string} metadata.description Image description (sanitized HTML).
 * @property {string} metadata.descriptionUrl File description page URL.
 * @property {string} metadata.fullUrl URL of full-sized image.
 * @property {string} metadata.thumbUrl URL of image thumbnail used in the toolbar dialog.
 * @property {number} metadata.originalWidth Width of original image in pixels.
 * @property {number} metadata.originalHeight Height of original image in pixels.
 */

/**
 * @typedef mw.libs.ge.ImageRecommendation
 * @property {mw.libs.ge.ImageRecommendationImage[]} images Recommended images.
 * @property {string} datasetId Dataset version ID.
 */

/**
 * @typedef mw.libs.ge.ImageRecommendationSummary
 * @property {boolean} accepted Whether the image was accepted
 * @property {string} filename Image file name
 * @property {string} thumbUrl URL of the image thumbnail
 * @property {string} caption Entered value for the image caption
 *
 */

/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by AddImageDesktopArticleTarget and
 * AddImageMobileArticleTarget.
 *
 * @mixin mw.libs.ge.AddImageArticleTarget
 * @extends ve.init.mw.ArticleTarget
 * @extends mw.libs.ge.StructuredTaskArticleTarget
 */
function AddImageArticleTarget() {
	/**
	 * @property {boolean|null} recommendationAccepted Recommendation acceptance status
	 *   (true: accepted; false: rejected; null: not decided yet).
	 */
	this.recommendationAccepted = null;

	/**
	 * @property {string[]} recommendationRejectionReasons List of rejection reason IDs.
	 */
	this.recommendationRejectionReasons = [];
}

AddImageArticleTarget.prototype.beforeSurfaceReady = function () {
	// TODO: Set fragments on surface
};

/**
 * Show the image inspector, hide save button
 */
AddImageArticleTarget.prototype.afterSurfaceReady = function () {
	// Set a reference to the toolbar up front so that it's available in subsequent calls
	// (since VeUiTargetToolbar is constructed upon these method calls if it's not there)
	this.targetToolbar = OO.ui.isMobile() ? this.getToolbar() : this.getActions();
	// Save button will be shown during caption step
	this.toggleSaveTool( false );

	if ( this.isValidTask() ) {
		this.getSurface().executeCommand( 'recommendedImage' );
	} else {
		// Ideally, this error would happen sooner so the user doesn't have to wait for VE
		// to load. There isn't really a way to differentiate between images in the article
		// and transcluded images without loading VE and loading the parsoid HTML, though.
		StructuredTaskPreEdit.showErrorDialogOnFailure();
	}
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.hasEdits = function () {
	return this.recommendationAccepted === true;
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.hasReviewedSuggestions = function () {
	return this.recommendationAccepted === true || this.recommendationAccepted === false;
};

/**
 * Check if the current article is a valid Add Image task (does not have any image yet).
 * @return {boolean}
 */
AddImageArticleTarget.prototype.isValidTask = function () {
	var surfaceModel = this.getSurface().getModel();

	if ( surfaceModel.getDocument().getNodesByType( 'mwBlockImage' ).length ||
		surfaceModel.getDocument().getNodesByType( 'mwInlineImage' ).length
	) {
		return false;
	}
	// TODO check for images in infoboxes, once we support articles with infoboxes.
	return true;
};

/**
 * Add the recommended image to the VE document.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 */
AddImageArticleTarget.prototype.insertImage = function ( imageData ) {
	var linearModel, insertOffset, dimensions,
		surfaceModel = this.getSurface().getModel(),
		data = surfaceModel.getDocument().data,
		NS_FILE = mw.config.get( 'wgNamespaceIds' ).file,
		imageTitle = new mw.Title( imageData.image, NS_FILE ),
		thumb = mw.util.parseImageUrl( imageData.metadata.thumbUrl );

	// Define the image to be inserted.
	// This will eventually be passed as the data parameter to MWBlockImageNode.toDomElements.
	// See also https://www.mediawiki.org/wiki/Specs/HTML/2.2.0#Images

	dimensions = ve.dm.MWImageNode.static.scaleToThumbnailSize( {
		width: imageData.metadata.originalWidth,
		height: imageData.metadata.originalHeight
	} );
	linearModel = [
		{
			type: 'mwGeRecommendedImage',
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
				filename: imageData.image,
				originalClasses: [ 'mw-default-size' ],
				isError: false,
				mw: {}
			},
			internal: {
				whitespace: [ '\n', undefined, undefined, '\n' ]
			}
		},
		{ type: 'mwGeRecommendedImageCaption' },
		{ type: 'paragraph', internal: { generated: 'wrapper' } },
		// Caption will be spliced in here. In the linear model each character is a separate item.
		{ type: '/paragraph' },
		{ type: '/mwGeRecommendedImageCaption' },
		{ type: '/mwGeRecommendedImage' }
	];

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
	this.hasStartedCaption = true;
};

/**
 * Check whether a given offset in the linear model is the end of the leading metadata block
 * (in an editorial sense, not a technical sense).
 *
 * @param {ve.dm.ElementLinearData} data
 * @param {number} offset
 * @return {boolean}
 * @private
 */
AddImageArticleTarget.prototype.isEndOfMetadata = function ( data, offset ) {
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
 * Undo the last change. Can be used to undo insertImage().
 */
AddImageArticleTarget.prototype.rollback = function () {
	var surfaceModel = this.getSurface().getModel(), recommendedImageNodes;
	surfaceModel.setReadOnly( false );
	recommendedImageNodes = surfaceModel.getDocument().getNodesByType( 'mwGeRecommendedImage' );
	recommendedImageNodes.forEach( function ( node ) {
		surfaceModel.getLinearFragment( node.getOuterRange() ).delete();
	} );
	surfaceModel.setReadOnly( true );
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.save = function ( doc, options, isRetry ) {
	/** @type {mw.libs.ge.ImageRecommendation} */
	var taskData = suggestedEditSession.taskData;

	options.plugins = 'ge-task-image-recommendation';
	// This data will be processed in HomepageHooks::onVisualEditorApiVisualEditorEditPostSaveHook
	options[ 'data-ge-task-image-recommendation' ] = JSON.stringify( {
		filename: taskData.images[ 0 ].image,
		accepted: this.recommendationAccepted,
		reasons: this.recommendationRejectionReasons
	} );
	return this.constructor.super.prototype.save.call( this, doc, options, isRetry );
};

/**
 * Get the image and its acceptance data to be used in the save dialog
 *
 * @return {mw.libs.ge.ImageRecommendationSummary}
 */
AddImageArticleTarget.prototype.getSummaryData = function () {
	var imageData = suggestedEditSession.taskData.images[ 0 ],
		surfaceModel = this.getSurface().getModel(),
		/** @type {mw.libs.ge.ImageRecommendationSummary} */
		summaryData = {
			filename: imageData.image,
			accepted: this.recommendationAccepted,
			thumbUrl: imageData.metadata.thumbUrl,
			caption: ''
		};
	if ( this.recommendationAccepted ) {
		var documentDataModel = surfaceModel.getDocument(),
			captionNode = documentDataModel.getNodesByType( 'mwGeRecommendedImageCaption' )[ 0 ],
			caption = surfaceModel.getLinearFragment( captionNode.getRange() ).getText();
		summaryData.caption = caption;
	}
	return summaryData;
};

/**
 * Get the save tool group
 *
 * @return {OO.ui.ToolGroup}
 */
AddImageArticleTarget.prototype.getSaveToolGroup = function () {
	return this.targetToolbar.getToolGroupByName( 'save' );
};

/**
 * Get the edit mode tool group
 *
 * @return {OO.ui.ToolGroup}
 */
AddImageArticleTarget.prototype.getEditModeToolGroup = function () {
	return this.targetToolbar.getToolGroupByName( 'suggestionsEditMode' );
};

/**
 * Toggle the visibility of the save tool
 *
 * @param {boolean} shouldShow Whether the save tool should be shown
 */
AddImageArticleTarget.prototype.toggleSaveTool = function ( shouldShow ) {
	var saveToolGroup = this.getSaveToolGroup();
	if ( saveToolGroup ) {
		saveToolGroup.toggle( shouldShow );
	}
};

/**
 * Set the disabled state of the save tool
 *
 * @param {boolean} shouldDisable Whether the save tool should be disabled
 */
AddImageArticleTarget.prototype.setDisabledSaveTool = function ( shouldDisable ) {
	var saveToolGroup = this.getSaveToolGroup();
	if ( saveToolGroup ) {
		saveToolGroup.setDisabled( shouldDisable );
	}
};

/**
 * Toggle the visibility of the edit mode tool
 *
 * @param {boolean} shouldShow Whether the edit mode tool should be shown
 */
AddImageArticleTarget.prototype.toggleEditModeTool = function ( shouldShow ) {
	var editModeToolGroup = this.getEditModeToolGroup();
	if ( editModeToolGroup ) {
		editModeToolGroup.toggle( shouldShow );
	}
};

module.exports = AddImageArticleTarget;
