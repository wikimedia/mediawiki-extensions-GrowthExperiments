var AddImageArticleTarget = require( '../addimage/AddImageArticleTarget.js' );

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

	/** @inheritDoc */
	this.ADD_IMAGE_CAPTION_ONBOARDING_PREF = 'growthexperiments-addsectionimage-caption-onboarding';

	/** @inheritDoc */
	this.CAPTION_INFO_DIALOG_NAME = 'addSectionImageCaptionInfo';

	/** @inheritDoc */
	this.QUALITY_GATE_WARNING_KEY = 'gesectionimagerecommendationdailytasksexceeded';

}
// We can't use normal inheritance because OO.mixinClass only copies own properties. Instead we
// mix in the pseudo-parent class. The end result is almost the same, we just need to set
// 'super' manually.
OO.mixinClass( AddSectionImageArticleTarget, AddImageArticleTarget );
AddSectionImageArticleTarget.super = AddImageArticleTarget;

/** @inheritDoc */
AddSectionImageArticleTarget.prototype.isValidTask = function () {
	// Tasks can be invalid in three ways:
	// - the section cannot be located (it has been removed, or moved, or its title changed);
	// - the section already contains an image;
	// - the recommended image is already used in another section.
	// Theoretically there are other possibilities (the section has been edited and is now too
	// short to be a good candidate, or its content changed to such an extent that its topic is
	// now different), but those are rare and would be hard or impossible to detect here.

	var imageTitle,
		imageNodes = [],
		surfaceModel = this.getSurface().getModel(),
		// We break our unused abstraction here. If we actually used multiple image recommendations,
		// they could belong to different sections, and then validity would have to be determined
		// per-image, not per-task.
		imageData = this.images[ this.selectedImageIndex ],
		insertRange = this.getInsertRange( imageData );

	if ( !insertRange ) {
		// The error was already logged in getInsertRange().
		return false;
	}

	/** @type {ve.dm.Node[]} imageNodes */
	imageNodes = imageNodes.concat( surfaceModel.getDocument().getNodesByType( 'mwBlockImage' ) );
	imageNodes = imageNodes.concat( surfaceModel.getDocument().getNodesByType( 'mwInlineImage' ) );
	for ( var i = 0; i < imageNodes.length; i++ ) {
		if ( insertRange.containsOffset( imageNodes[ i ].getOffset() ) ) {
			mw.log.error( 'Section ' + imageData.sectionNumber + ' already contains an image: ' +
				imageNodes[ i ].getAttribute( 'resource' ) );
			return false;
		}
		imageTitle = mw.Title.newFromText(
			// Parsoid filename attributes start with "./".
			imageNodes[ i ].getAttribute( 'resource' ).slice( 2 )
		);
		if ( imageTitle.getMain() === imageData.image ) {
			mw.log.error( 'Image ' + imageData.image + ' is already used in another section' );
			return false;
		}
	}
	return true;
};

/** @inheritDoc */
AddSectionImageArticleTarget.prototype.getInsertRange = function ( imageData ) {
	var domElements, titleText, heading, nextHeading,
		h2Count = 0,
		surfaceModel = this.getSurface().getModel(),
		headingNodes = surfaceModel.getDocument().getNodesByType( 'mwHeading' );

	for ( var i = 0; i < headingNodes.length; i++ ) {
		if ( headingNodes[ i ].getAttribute( 'level' ) !== 2 ) {
			// Currently only recommending for top-level headings.
			continue;
		}
		h2Count++;
		// FIXME accept null section numbers for testing convenience as the dataset hasn't been
		//   fully initialized yet.
		if ( !heading && ( h2Count === imageData.sectionNumber || imageData.sectionNumber === null ) ) {
			// The article might have been edited since. Double-check that the title text matches.
			// imageData.sectionTitle is wikitext and lowercased so this will be somewhat fragile.
			domElements = headingNodes[ i ].getOriginalDomElements( headingNodes[ i ].getStore() );
			titleText = $( '<div>' ).append( $( domElements ).clone() ).prop( 'innerText' ).toLowerCase();
			if ( titleText === imageData.sectionTitle ||
				domElements[ 0 ] instanceof HTMLHeadingElement &&
					domElements[ 0 ].id.replace( /_/g, ' ' ).toLowerCase() === imageData.sectionTitle
			) {
				heading = headingNodes[ i ];
				imageData.visibleSectionTitle = titleText;
			} else {
				mw.log.error(
					'Section title mismatch for section ' + imageData.sectionNumber + ': ' +
						'expected "' + imageData.sectionTitle + '", got "' + titleText + '"'
				);
				break;
			}
		} else if ( heading ) {
			nextHeading = headingNodes[ i ];
			break;
		}
	}

	if ( nextHeading ) {
		return new ve.Range(
			heading.getOuterRange( false ).end,
			nextHeading.getOuterRange( false ).start
		);
	} else if ( heading ) {
		return new ve.Range(
			heading.getOuterRange( false ).end,
			surfaceModel.getDocument().getDocumentRange().end
		);
	} else {
		if ( h2Count < imageData.sectionNumber ) {
			mw.log.error( 'Section ' + imageData.sectionNumber + ' not found, the article only has ' +
				h2Count + ' h2 sections' );
		}
		return null;
	}
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
