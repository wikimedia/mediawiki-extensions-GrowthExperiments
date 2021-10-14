var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

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
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by AddImageDesktopArticleTarget and
 * AddImageMobileArticleTarget.
 *
 * @mixin mw.libs.ge.AddImageArticleTarget
 * @extends ve.init.mw.ArticleTarget
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

AddImageArticleTarget.prototype.afterSurfaceReady = function () {
	this.getSurface().executeCommand( 'recommendedImage' );
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
	var surfaceModel = this.getSurface().getModel();
	surfaceModel.setReadOnly( false );
	surfaceModel.undo();
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

module.exports = AddImageArticleTarget;
