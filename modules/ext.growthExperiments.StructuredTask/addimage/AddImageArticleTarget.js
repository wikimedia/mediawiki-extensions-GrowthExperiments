const StructuredTaskPreEdit = require( 'ext.growthExperiments.StructuredTask.PreEdit' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
	MAX_IMAGE_DISPLAY_WIDTH = 500;

/**
 * @typedef mw.libs.ge.ImageRecommendationImage
 * @see ImageRecommendationImage PHP class
 * @property {string} image Image filename in unprefixed DBkey format.
 * @property {string} displayFilename Image filename with spaces instead of underscores.
 * @property {string} source Recommendation source; one of 'wikidata', 'wikipedia', 'commons'.
 * @property {string[]} projects Lists of projects (as wiki IDs) the recommendation is from.
 *   Only used when the source is 'wikipedia'.
 * @property {number|null} sectionNumber Section number for which the image is recommended
 *   (1-based index of the section within the second-level sections), or null for top-level
 *   recommendations.
 * @property {string|null} sectionTitle Wikitext section title of the section the image is
 *   recommended for, or null for top-level recommendations. This is often but not always
 *   the same as the ID of the section heading (apart from a space -> underscore
 *   transformation). It might differ if the section title contains wikitext formatting, the
 *   parser does something weird with plaintext in the given language (e.g. french spaces), or
 *   the ID gets numbered because there are multiple identical headings on the page.
 * @property {string?} visibleSectionTitle Visible section title (i.e. the plaintext version of
 *   the HTML section title) of the section the image is recommended for, or undefined for
 *   top-level. This is a hack as the server has no easy way to provide it; it will be set in
 *   getInsertRange().
 * @property {Object} metadata See ImageRecommendationMetadataProvider::getMetadata()
 * @property {string} metadata.descriptionUrl File description page URL.
 * @property {string} metadata.fullUrl URL of full-sized image.
 * @property {string} metadata.thumbUrl URL of image thumbnail used in the toolbar dialog.
 * @property {number} metadata.originalWidth Width of original image in pixels.
 * @property {number} metadata.originalHeight Height of original image in pixels.
 * @property {boolean} metadata.mustRender True if the original image wouldn't display correctly
 *   in a browser.
 * @property {boolean} metadata.isVectorized Whether the image is a vector image (ie. has no max size).
 * @property {string|null} metadata.description Image description (sanitized HTML).
 * @property {string|null} metadata.author Original author of image (sanitized HTML).
 * @property {string|null} metadata.license Short license name (sanitized HTML).
 * @property {string} metadata.date Date of original image creation (sanitized HTML).
 * @property {string|null} metadata.caption MediaInfo caption (plain text).
 * @property {string[]} metadata.categories Non-hidden categories of the image, in
 *   mw.Title.getMainText() format.
 * @property {string} metadata.reason Description of why the image is being recommended (plain text).
 * @property {string} metadata.contentLanguageName Name of the content language (plain text).
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
 * @property {string} caption Entered value for the image caption
 * @property {mw.libs.ge.RecommendedImageMetadata} metadata Image metadata
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

	/**
	 * @property {number} selectedImageIndex Zero-based index of the selected image suggestion.
	 */
	this.selectedImageIndex = 0;

	/**
	 * @property {number} insertOffset Internal to insertImage/isEndOfMetadata
	 * @private
	 */
	this.insertOffset = 0;

	/** @property {?ve.dm.Transaction} The document change performed when the user aproved the suggestion. */
	this.approvalTransaction = null;

	// define values that differ between top-level and section-level section recommendations
	// as constants so AddSectionImageArticleTarget can override them

	/** @property {string} TASK_TYPE_ID Task type ID */
	this.TASK_TYPE_ID = 'image-recommendation';

	/** @property {string} ADD_IMAGE_CAPTION_ONBOARDING_PREF User preference for onboarding seen flag */
	this.ADD_IMAGE_CAPTION_ONBOARDING_PREF = 'growthexperiments-addimage-caption-onboarding';

	/** @property {string} CAPTION_INFO_DIALOG_NAME Name of the caption info help window */
	this.CAPTION_INFO_DIALOG_NAME = 'addImageCaptionInfo';

	/** @property {string} QUALITY_GATE_WARNING_KEY Warning key set by the submission handler */
	this.QUALITY_GATE_WARNING_KEY = 'geimagerecommendationdailytasksexceeded';
}

/** @return {boolean} */
AddImageArticleTarget.prototype.isSectionLevelTask = function () {
	return this.TASK_TYPE_ID !== 'image-recommendation';
};

AddImageArticleTarget.prototype.beforeStructuredTaskSurfaceReady = function () {
	/**
	 * @property {mw.libs.ge.ImageRecommendationImage[]} images
	 */
	this.images = suggestedEditSession.taskData.images;
};

/**
 * Show the image inspector, hide save button
 */
AddImageArticleTarget.prototype.afterStructuredTaskSurfaceReady = function () {
	if ( this.isValidTask() ) {
		// Set a reference to the toolbar up front so that it's available in subsequent calls
		// (since VeUiTargetToolbar is constructed upon these method calls if it's not there)
		this.targetToolbar = OO.ui.isMobile() ? this.getToolbar() : this.getActions();
		// Save button will be shown during caption step
		this.toggleSaveTool( false );
		if ( OO.ui.isMobile() ) {
			this.setupTask();
		} else {
			// On desktop, onboarding is shown after the editor loads and the inspector is shown
			// upon closing onboarding.
			mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add(
				this.setupTask.bind( this )
			);
			mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).fire();
		}
		this.logger.log( 'impression', {}, {
			// eslint-disable-next-line camelcase
			active_interface: 'machinesuggestions_mode'
		} );
		this.getSurface().getView().$element.on( 'paste', this.onPaste.bind( this ) );
	} else {
		// Ideally, this error would happen sooner so the user doesn't have to wait for VE
		// to load. There isn't really a way to differentiate between images in the article
		// and transcluded images without loading VE and loading the parsoid HTML, though.
		this.invalidateRecommendation();
		StructuredTaskPreEdit.showErrorDialogOnFailure();
	}
};

/**
 * Set up the structured task. This should be called when the user is able to start interacting
 * with the VE surface.
 */
AddImageArticleTarget.prototype.setupTask = function () {
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
 * Check if the current article is a valid Add Image task (does not have any image yet).
 *
 * @return {boolean}
 */
AddImageArticleTarget.prototype.isValidTask = function () {
	const surfaceModel = this.getSurface().getModel();

	if ( surfaceModel.getDocument().getNodesByType( 'mwBlockImage' ).length ||
		surfaceModel.getDocument().getNodesByType( 'mwInlineImage' ).length
	) {
		return false;
	}
	// TODO check for images in infoboxes, once we support articles with infoboxes.
	return true;
};

/**
 * Add something to the VE document at the location where the recommended image should go.
 * Used to insert either the image or a placeholder.
 *
 * @param {Array} linearModel VisualEditor linear model
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 * @return {ve.dm.Transaction}
 */
AddImageArticleTarget.prototype.insertLinearModelAtRecommendationLocation = function (
	linearModel,
	imageData
) {
	const surface = this.getSurface(),
		surfaceModel = surface.getModel(),
		data = surfaceModel.getDocument().data;

	// Find the position between the initial templates and text.
	const insertRange = this.getInsertRange( imageData );
	let relativeOffset;
	if ( insertRange === null ) {
		relativeOffset = -1;
	} else {
		this.insertOffset = insertRange.start;
		// <offset>, 0 means start from that offset and only move if it is invalid.
		// <offset>, 1 would always move.
		relativeOffset = data.getRelativeOffset(
			this.insertOffset,
			0,
			// This will update insertOffset.
			( offset ) => this.isEndOfMetadata( data, offset )
		);
		if ( this.insertOffset > insertRange.end ) {
			relativeOffset = -1;
		}
	}
	if ( relativeOffset === -1 ) {
		// No valid position found.
		mw.log.error( 'No valid offset found for image insertion' );
		mw.errorLogger.logError( new Error( 'No valid offset found for image insertion' ),
			'error.growthexperiments' );
		// If range were null, this wouldn't be a good way to deal with it - we'd fall back
		// to the top of the article for a section image recommendation. But in that case,
		// isValidTask() will return false so we never get here.
		this.insertOffset = insertRange ? insertRange.start : 0;
	}

	// Actually insert the image.
	surfaceModel.setReadOnly( false );
	const transaction = ve.dm.TransactionBuilder.static.newFromInsertion(
		surfaceModel.getDocument(),
		this.insertOffset,
		linearModel
	);
	surfaceModel.change(
		transaction,
		new ve.dm.LinearSelection( new ve.Range( this.insertOffset ) )
	);
	surfaceModel.setReadOnly( true );

	return transaction;
};

/**
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 * @return {{width:number, height: number}}
 */
AddImageArticleTarget.prototype.getImageDimensions = function ( imageData ) {
	const surface = this.getSurface();

	const originalDimensions = {
		width: imageData.metadata.originalWidth,
		height: imageData.metadata.originalHeight
	};

	// On mobile, the image is rendered full width (with max width set to account for tablets).
	// On desktop, the default thumbnail size is used.
	const targetWidth = surface.getContext().isMobile() ?
		Math.min(
			originalDimensions.width,
			// Use the editor overlay element instead of the surface since its width is not
			// computed while VE is not loaded (eg: slow connections). The overlay width is the
			// same as the surface on phones and it will be capped by MAX_IMAGE_DISPLAY_WIDTH on
			// tablet.
			this.$element.width(),
			MAX_IMAGE_DISPLAY_WIDTH
		) : this.getDefaultThumbSize();

	return {
		width: targetWidth,
		height: Math.round( targetWidth * ( originalDimensions.height / originalDimensions.width ) )
	};
};

/**
 * Define the image to be inserted.
 * This will eventually be passed as the data parameter to MWBlockImageNode.toDomElements.
 * See also https://www.mediawiki.org/wiki/Specs/HTML/2.2.0#Images
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 * @return {Array}
 */
AddImageArticleTarget.prototype.getImageLinearModel = function ( imageData ) {
	const NS_FILE = mw.config.get( 'wgNamespaceIds' ).file,
		imageTitle = new mw.Title( imageData.image, NS_FILE ),
		AddImageUtils = require( './AddImageUtils.js' );

	const dimensions = this.getImageDimensions( imageData );
	const imageRenderData = AddImageUtils.getImageRenderData( imageData.metadata, window, dimensions.width );
	return [
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
				width: dimensions.width,
				height: dimensions.height,
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
		{
			type: 'mwGeRecommendedImageCaption',
			attributes: {
				taskType: this.TASK_TYPE_ID,
				visibleSectionTitle: imageData.visibleSectionTitle || null
			}
		},
		{ type: 'paragraph', internal: { generated: 'wrapper' } },
		// Caption will be spliced in here. In the linear model each character is a separate item.
		{ type: '/paragraph' },
		{ type: '/mwGeRecommendedImageCaption' },
		{ type: '/mwGeRecommendedImage' }
	];
};

/**
 * Add the recommended image to the VE document.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 */
AddImageArticleTarget.prototype.insertImage = function ( imageData ) {
	this.approvalTransaction = this.insertLinearModelAtRecommendationLocation(
		this.getImageLinearModel( imageData )
	);
	this.hasStartedCaption = true;
};

/**
 * Get the range in which the image can be inserted. The image will be inserted towards the
 * top of that range, after templates.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 * @return {ve.Range|null} Null when the image shouldn't be inserted.
 */
AddImageArticleTarget.prototype.getInsertRange = function (
	// eslint-disable-next-line no-unused-vars
	imageData
) {
	return this.getSurface().getModel().getDocument().getDocumentRange();
};

/**
 * Check whether a given offset in the linear model is the end of the leading metadata block
 * (in an editorial sense, not a technical sense).
 *
 * The returned value is used by ve.dm.ElementLinearData.getRelativeOffset, but the real work is
 * done by setting insertOffset. Basically we are doing a lookahead, setting insertOffset to a
 * candidate insertion offset and using getRelativeOffset's current position to look for text
 * content after that offset. The idea is to differentiate between e.g.
 *
 * <block template /> <paragraph> <invisible inline template /> t e x t </paragraph>
 *                   ^                                         ^
 *                  good                                       X
 *
 * and
 *
 * <block template /> <paragraph> <invisible inline template /> </paragraph> <block template />
 *                   ^                                                      ^
 *                  bad                                                     X
 *
 * while X is the earliest point where we can tell whether that candidate position was good or bad.
 *
 * @param {ve.dm.ElementLinearData} data
 * @param {number} offset
 * @return {boolean}
 * @private
 */
AddImageArticleTarget.prototype.isEndOfMetadata = function ( data, offset ) {
	// Limit to offsets where a block (such as our image) can be added.
	// ve.dm.SurfaceFragment.prototype.insertContent can handle inserting a block to a content
	// offset (which requires splitting the parent paragraph in two to turn it into a structural
	// offset), but the first position that separates top-of-the-article boilerplate content
	// from article text content is always going to be a structural offset, since the boilerplates
	// are all blocks.
	if ( data.isStructuralOffset( offset, true ) ) {
		this.insertOffset = offset;
	} else if ( !data.isContentOffset( offset ) ) {
		// We are not interested in offsets where neither text nor arbitrary blocks can go.
		// That would be something like a list or a table row.
		return false;
	}

	const right = data.getData( offset );
	if ( typeof right === 'string' ) {
		// If we found text content, exit, except for whitespace, which can be between
		// top-of-the-article templates.
		return !right.match( /\s/ );
	} else if ( Array.isArray( right ) ) {
		// Annotated text. No one would annotate empty whitespace so we can skip the check.
		return true;
	} else if ( right.type.charAt( 0 ) === '/' ) {
		// It's always fine to move out of something. The image is at the top level.
		// For section images, MediaWiki section headers can appear in weird places
		// (inside a table etc), but the recommender system will skip over those.
		return false;
	}

	// right is an opening tag. Skip it if it's a template or invisible metadata.
	// None of these can have children, which makes life a lot easier for us.
	if ( [
		// templates
		'mwTransclusion', 'mwTransclusionBlock', 'mwTransclusionInline', 'mwTransclusionTableCell',
		// ve.dm.MetaItem subclasses
		'mwAlienMeta', 'mwCategory', 'mwDefaultSort', 'mwDisplayTitle', 'mwHiddenCategory',
		'mwIndex', 'mwLanguage', 'mwNewSectionEdit', 'mwNoContentConvert', 'mwNoEditSection',
		'mwNoGallery', 'mwNoTitleConvert', 'mwDisambiguation',
		// hidden, so probably should go before the image
		'comment', 'mwLanguageVariantHidden',
		// automatically generated to wrap content, which could be templates
		'paragraph'
	].indexOf( right.type ) !== -1 ) {
		return false;
	}
	return true;
};

/**
 * Replace the image placeholder with the actual image, when the user has approved it.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 */
AddImageArticleTarget.prototype.replacePlaceholderWithImage = function () {
	throw new Error( 'Should only be called on the child class' );
};

/**
 * Undo the last change. Can be used to undo insertImage() or replacePlaceholderWithImage().
 */
AddImageArticleTarget.prototype.rollback = function () {
	const surfaceModel = this.getSurface().getModel();
	if ( !this.approvalTransaction ) {
		return;
	}

	surfaceModel.setReadOnly( false );
	surfaceModel.change( this.approvalTransaction.reversed() );
	surfaceModel.setReadOnly( true );
	this.approvalTransaction = null;
	this.updateSuggestionState( this.selectedImageIndex, null, [] );
	this.hasStartedCaption = false;
};

/**
 * Insert the specified caption text.
 * This is used when the caption text needs to be programmatically updated, such as in the
 * custom paste handler.
 *
 * @param {string} captionText
 */
AddImageArticleTarget.prototype.insertCaption = function ( captionText ) {
	const surfaceModel = this.getSurface().getModel(),
		documentModel = surfaceModel.getDocument(),
		captionNode = documentModel.getNodesByType( 'mwGeRecommendedImageCaption' )[ 0 ],
		captionNodeRange = captionNode.getRange( false ),
		selection = surfaceModel.getSelection().getRange();

	let insertOffset;
	if ( captionNodeRange.containsRange( selection ) ) {
		insertOffset = selection;
	} else {
		insertOffset = surfaceModel.getDocument().getRelativeRange(
			new ve.Range( captionNodeRange.start ), 1, 'character', false
		);
	}
	// Insert the caption without auto-selection and move the cursor to the end of the insertion
	surfaceModel.getLinearFragment( insertOffset, true )
		.insertContent( captionText )
		.collapseToEnd().select();
};

/**
 * Only allow plain text to be pasted in as caption.
 * This overrides VE's default paste handler which supports rich formatting.
 *
 * @param {jQuery.Event} e
 */
AddImageArticleTarget.prototype.onPaste = function ( e ) {
	if ( !this.hasStartedCaption ) {
		return;
	}
	e.preventDefault();
	e.stopPropagation();
	const text = ( e.originalEvent.clipboardData || window.clipboardData ).getData( 'text' );
	this.insertCaption( text );
};

/**
 * Get the caption value
 *
 * @return {string}
 */
AddImageArticleTarget.prototype.getCaptionText = function () {
	const surfaceModel = this.getSurface().getModel(),
		documentDataModel = surfaceModel.getDocument(),
		captionNode = documentDataModel.getNodesByType( 'mwGeRecommendedImageCaption' )[ 0 ];
	return surfaceModel.getLinearFragment( captionNode.getRange() ).getText() || '';
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.save = function ( doc, options, isRetry ) {
	options.plugins = 'ge-task-' + this.TASK_TYPE_ID;
	// This data will be processed in VisualEditorHooks::onVisualEditorApiVisualEditorEditPostSave
	options[ 'data-ge-task-' + this.TASK_TYPE_ID ] = JSON.stringify( this.getVEPluginData() );
	return this.constructor.super.prototype.save.call( this, doc, options, isRetry )
		.done( () => {
			this.madeNullEdit = !this.recommendationAccepted;
			this.onSaveDone();
		} );
};

/**
 * Plugin data to pass to VisualEditorApiVisualEditorEdit(Pre|Post)SaveHook.
 *
 * @return {Object}
 */
AddImageArticleTarget.prototype.getVEPluginData = function () {
	return {
		taskType: this.TASK_TYPE_ID,
		filename: this.getSelectedSuggestion().image,
		accepted: !!this.recommendationAccepted,
		reasons: this.recommendationRejectionReasons,
		caption: this.recommendationAccepted ? this.getCaptionText() : '',
		// The section parameters will always be null for image-recommendation tasks, but
		// non-null for section-image-recommendation tasks. They are required parameters for both.
		sectionNumber: this.getSelectedSuggestion().sectionNumber,
		sectionTitle: this.getSelectedSuggestion().sectionTitle
	};
};

/**
 * Called after the save is done but before the surface is torn down
 */
AddImageArticleTarget.prototype.onSaveDone = function () {
	// intentionally no-op
};

/**
 * Get the image and its acceptance data to be used in the save dialog
 *
 * @return {mw.libs.ge.ImageRecommendationSummary}
 */
AddImageArticleTarget.prototype.getSummaryData = function () {
	const imageData = this.getSelectedSuggestion(),
		/** @type {mw.libs.ge.ImageRecommendationSummary} */
		summaryData = {
			filename: imageData.displayFilename,
			accepted: this.recommendationAccepted,
			metadata: imageData.metadata,
			caption: ''
		};
	if ( this.recommendationAccepted ) {
		summaryData.caption = this.getCaptionText();
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
	const saveToolGroup = this.getSaveToolGroup();
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
	const saveToolGroup = this.getSaveToolGroup();
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
	const editModeToolGroup = this.getEditModeToolGroup();
	if ( editModeToolGroup ) {
		editModeToolGroup.toggle( shouldShow );
	}
};

/**
 * Show the caption info dialog.
 *
 * When the dialog is opened automatically (when the user accepts a suggestion and hasn't dismissed
 * the caption onboarding dialog), the user preference should be checked to determine if the user
 * has opted out of seeing the dialog. When the dialog is opened via a click on the help button
 * during caption step, the preference doesn't need to be checked.
 *
 * By default, the dialog is opened without checking the user's preference.
 *
 * @param {boolean} [shouldCheckPref] Whether the dialog preference should be taken into account
 */
AddImageArticleTarget.prototype.showCaptionInfoDialog = function ( shouldCheckPref ) {
	const dialogName = this.CAPTION_INFO_DIALOG_NAME,
		logCaptionInfoDialog = function ( action, context, closeData ) {
			/* eslint-disable camelcase */
			const actionData = Object.assign(
				this.getSuggestionLogActionData(),
				{ dialog_context: context }
			);
			if ( closeData ) {
				actionData.dont_show_again = closeData.dialogDismissed;
			}
			this.logger.log( action, actionData, { active_interface: 'captioninfo_dialog' } );
			/* eslint-enable camelcase */
		}.bind( this );

	let openDialogPromise;
	if ( !shouldCheckPref ) {
		openDialogPromise = this.surface.dialogs.openWindow( dialogName );
		openDialogPromise.opening.then( () => {
			logCaptionInfoDialog( 'impression', 'help' );
		} );
		openDialogPromise.closed.then( () => {
			logCaptionInfoDialog( 'close', 'help' );
		} );
		return;
	}

	// The dialog was shown already during the session (the user went back from caption step)
	if ( this.hasShownCaptionOnboarding ) {
		return;
	}

	if ( !mw.user.options.get( this.ADD_IMAGE_CAPTION_ONBOARDING_PREF ) ) {
		this.hasShownCaptionOnboarding = true;
		openDialogPromise = this.surface.dialogs.openWindow(
			dialogName,
			{ shouldShowDismissField: true }
		);
		openDialogPromise.opening.then( () => {
			logCaptionInfoDialog( 'impression', 'onboarding' );
		} );
		openDialogPromise.closed.then( ( closeData ) => {
			logCaptionInfoDialog( 'close', 'onboarding', closeData );
		} );
	}
};

/**
 * Get data for the suggestion at the specified index (if any) or the selected suggestion
 * to pass to ImageSuggestionInteractionLogger.
 *
 * @param {number} [index] Zero-based index of the image suggestion for which to return data
 * @return {Object}
 */
AddImageArticleTarget.prototype.getSuggestionLogActionData = function ( index ) {
	const imageIndex = typeof index === 'number' ? index : this.selectedImageIndex,
		imageData = this.images[ imageIndex ],
		isUndecided = typeof this.recommendationAccepted !== 'boolean',
		acceptanceState = this.recommendationAccepted ? 'accepted' : 'rejected';
	return {
		/* eslint-disable camelcase */
		filename: imageData.image,
		recommendation_source: imageData.source,
		recommendation_source_projects: imageData.projects,
		series_number: imageIndex + 1,
		total_suggestions: this.images.length,
		rejection_reasons: this.recommendationRejectionReasons,
		acceptance_state: isUndecided ? 'undecided' : acceptanceState
		/* eslint-enable camelcase */
	};
};

/**
 * Log actions specific to the current suggestion
 *
 * @param {string} action Name of the action the user took
 * @param {string} activeInterface Name of the current interface
 * @param {Object} [actionData] Additional action data
 */
AddImageArticleTarget.prototype.logSuggestionInteraction = function (
	action, activeInterface, actionData ) {
	this.logger.log(
		action,
		Object.assign( actionData || {}, this.getSuggestionLogActionData() ),
		// eslint-disable-next-line camelcase
		{ active_interface: activeInterface }
	);
};

/**
 * Update the state of the image suggestion at the specified index
 *
 * @param {number} index Zero-based index of the image suggestion being updated
 * @param {boolean|null} accepted Whether the image suggestion is accepted;
 *  null indicates that the user hasn't decided.
 * @param {string[]} reasons List of rejection reason IDs (when accepted is false)
 */
AddImageArticleTarget.prototype.updateSuggestionState = function (
	index, accepted, reasons
) {
	this.selectedImageIndex = index;
	this.recommendationAccepted = accepted;
	this.recommendationRejectionReasons = reasons;
};

/**
 * Return the selected image suggestion data
 *
 * @return {mw.libs.ge.ImageRecommendationImage}
 */
AddImageArticleTarget.prototype.getSelectedSuggestion = function () {
	return this.images[ this.selectedImageIndex ];
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.onSaveComplete = function ( data ) {
	const imageRecWarningKey = this.QUALITY_GATE_WARNING_KEY,
		geWarnings = data.gewarnings || [];

	geWarnings.forEach( function ( warning ) {
		if ( warning[ imageRecWarningKey ] ) {
			suggestedEditSession.qualityGateConfig[ this.TASK_TYPE_ID ] = { dailyLimit: true };
			suggestedEditSession.save();
		}
	} );
};

/**
 * Get the default thumbnail size in pixels
 *
 * @return {number}
 */
AddImageArticleTarget.prototype.getDefaultThumbSize = function () {
	const veConfig = mw.config.get( 'wgVisualEditorConfig' ) || {},
		thumbLimits = veConfig.thumbLimits || {};
	return thumbLimits[ mw.user.options.get( 'thumbsize' ) ] || 300;
};

/**
 * Set up loading states and promise for saving without showing the save dialog first.
 *
 * @abstract
 * @return {jQuery.Deferred}
 */
AddImageArticleTarget.prototype.prepareSaveWithoutShowingDialog = null;

/**
 * Save the article without showing the save dialog first.
 * This is used when the suggestion is rejected.
 */
AddImageArticleTarget.prototype.saveWithoutShowingDialog = function () {
	// When the save dialog is shown, the promise is from the ProcessDialog.
	// In this case, the promise is used to control the loading state.
	const promise = this.prepareSaveWithoutShowingDialog();
	this.onSaveDialogSave( promise );
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.formatSaveOptions = function ( saveOptions ) {
	// Edit summary will be localized in the content language via FormatAutocomments hook.
	saveOptions.summary = '/* growthexperiments-addimage-summary-summary: 1 */';
	return saveOptions;
};

/**
 * Invalidate the current task via growthinvalidateimagerecommendation API so that users don't see
 * the invalid task in their task queues.
 */
AddImageArticleTarget.prototype.invalidateRecommendation = function () {
	const imageData = this.getSelectedSuggestion();
	new mw.Api().postWithToken( 'csrf', {
		action: 'growthinvalidateimagerecommendation',
		title: suggestedEditSession.getCurrentTitle().getNameText(),
		// "image" is the unprefixed filename, e.g. Example.jpg
		filename: imageData.image,
		tasktype: this.TASK_TYPE_ID,
		sectiontitle: imageData.sectionTitle,
		sectionnumber: imageData.sectionNumber
	} );
};

module.exports = AddImageArticleTarget;
