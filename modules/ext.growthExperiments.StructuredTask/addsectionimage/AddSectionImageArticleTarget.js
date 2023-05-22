var AddImageArticleTarget = require( '../addimage/AddImageArticleTarget.js' ),
	MAX_IMAGE_DISPLAY_WIDTH = 500;

/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by AddSectionImageDesktopArticleTarget and
 * AddSectionImageMobileArticleTarget.
 *
 * @mixin mw.libs.ge.AddSectionImageArticleTarget
 * @extends mw.libs.ge.AddImageArticleTarget
 * @extends mw.libs.ge.StructuredTaskArticleTarget
 */
function AddSectionImageArticleTarget() {
	AddImageArticleTarget.apply( this, arguments );

	/** @inheritDoc */
	this.TASK_TYPE_ID = 'section-image-recommendation';

	// FIXME modify
	/** @inheritDoc */
	this.ADD_IMAGE_CAPTION_ONBOARDING_PREF = 'growthexperiments-addimage-caption-onboarding';

	// FIXME modify
	/** @inheritDoc */
	this.CAPTION_INFO_DIALOG_NAME = 'addImageCaptionInfo';

	/** @inheritDoc */
	this.QUALITY_GATE_WARNING_KEY = 'gesectionimagerecommendationdailytasksexceeded';

}
// We can't use normal inheritance because OO.mixinClass only copies own properties. Instead we
// mix in the pseudo-parent class. The end result is almost the same, we just need to set
// 'super' manually.
OO.mixinClass( AddSectionImageArticleTarget, AddImageArticleTarget );
AddSectionImageArticleTarget.super = AddImageArticleTarget;

/**
 * Check if the current article is a valid Add Image task (does not have any image yet).
 *
 * @return {boolean}
 */
AddSectionImageArticleTarget.prototype.isValidTask = function () {
	// FIXME check for unillustrated section instead

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
AddSectionImageArticleTarget.prototype.insertImage = function ( imageData ) {
	// FIXME insert into the appropriate section instead

	var linearModel, contentOffset, dimensions,
		surface = this.getSurface(),
		surfaceModel = surface.getModel(),
		data = surfaceModel.getDocument().data,
		NS_FILE = mw.config.get( 'wgNamespaceIds' ).file,
		imageTitle = new mw.Title( imageData.image, NS_FILE ),
		AddImageUtils = require( '../addimage/AddImageUtils.js' ),
		targetWidth, imageRenderData;

	// Define the image to be inserted.
	// This will eventually be passed as the data parameter to MWBlockImageNode.toDomElements.
	// See also https://www.mediawiki.org/wiki/Specs/HTML/2.2.0#Images

	dimensions = {
		width: imageData.metadata.originalWidth,
		height: imageData.metadata.originalHeight
	};
	// On mobile, the image is rendered full width (with max width set to account for tablets).
	// On desktop, the default thumbnail size is used.
	targetWidth = surface.getContext().isMobile() ?
		Math.min(
			dimensions.width,
			surface.getView().$documentNode.width(),
			MAX_IMAGE_DISPLAY_WIDTH
		) : this.getDefaultThumbSize();
	imageRenderData = AddImageUtils.getImageRenderData( imageData.metadata, window, targetWidth );
	linearModel = [
		{
			type: 'mwGeRecommendedImage',
			attributes: {
				mediaClass: 'File',
				mediaTag: 'img',
				// This is a Commons image so the link needs to use the English namespace name
				// but Title uses the localized one. That's OK, Parsoid will figure it out.
				// Native VE images also use localized titles.
				href: './' + imageTitle.getPrefixedText(),
				resource: './' + imageTitle.getPrefixedText(),
				type: 'thumb',
				defaultSize: true,
				// The generated wikitext will ignore width/height when defaultSize is set, but
				// it's still used for the visual size of the thumbnail in the editor.
				width: targetWidth,
				height: targetWidth * ( dimensions.height / dimensions.width ),
				src: imageRenderData.src,
				align: 'default',
				filename: imageData.image,
				originalClasses: [ 'mw-default-size' ],
				isError: false,
				mw: {},
				// Pass image recommendation metadata to CERecommendedImageNode
				recommendation: imageData,
				recommendationIndex: this.selectedImageIndex
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
	this.insertOffset = 0;
	// 0, 0 means start from offset 0 and only move if it is invalid. 0, 1 would always move.
	contentOffset = data.getRelativeOffset( 0, 0, function ( offset ) {
		return this.isEndOfMetadata( data, offset );
	}.bind( this ) );
	if ( contentOffset === -1 ) {
		// No valid position found. This shouldn't be possible.
		mw.log.error( 'No valid offset found for image insertion' );
		mw.errorLogger.logError( new Error( 'No valid offset found for image insertion' ),
			'error.growthexperiments' );
		this.insertOffset = 0;
	}

	// Actually insert the image.
	surfaceModel.setReadOnly( false );
	surfaceModel.getLinearFragment( new ve.Range( this.insertOffset ) ).insertContent( linearModel );
	surfaceModel.setReadOnly( true );
	this.hasStartedCaption = true;
};

/** @inheritDoc **/
AddSectionImageArticleTarget.prototype.getVEPluginData = function () {
	var pluginData = AddSectionImageArticleTarget.super.prototype.getVEPluginData.call( this );
	pluginData.sectionNumber = this.getSelectedSuggestion().sectionNumber;
	pluginData.sectionTitle = this.getSelectedSuggestion().sectionTitle;
	return pluginData;
};

/**
 * Get data for the suggestion at the specified index (if any) or the selected suggestion
 * to pass to ImageSuggestionInteractionLogger.
 *
 * @param {number} [index] Zero-based index of the image suggestion for which to return data
 * @return {Object}
 */
AddSectionImageArticleTarget.prototype.getSuggestionLogActionData = function ( index ) {
	var actionData,
		imageIndex = typeof index === 'number' ? index : this.selectedImageIndex,
		imageData = this.images[ imageIndex ],
		sectionNumber = imageData.sectionNumber,
		sectionTitle = imageData.sectionTitle;

	actionData = AddSectionImageArticleTarget.super.prototype.getSuggestionLogActionData.call( this, index );
	actionData.sectionNumber = sectionNumber;
	actionData.sectionTitle = sectionTitle;
	return actionData;
};

/** @inheritDoc **/
AddSectionImageArticleTarget.prototype.formatSaveOptions = function ( saveOptions ) {
	// FIXME modify or remove this method override
	saveOptions.summary = '/* growthexperiments-addimage-summary-summary: 1 */';
	return saveOptions;
};

module.exports = AddSectionImageArticleTarget;
