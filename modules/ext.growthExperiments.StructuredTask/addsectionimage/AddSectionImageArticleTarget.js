const AddImageArticleTarget = require( '../addimage/AddImageArticleTarget.js' );
let scrollingDeferred;

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

	/** @property {?ve.ce.HeadingNode} The heading for which the image is recommended. */
	this.targetHeading = null;
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

	const imageNodes = [],
		surfaceModel = this.getSurface().getModel(),
		// We break our unused abstraction here. If we actually used multiple image recommendations,
		// they could belong to different sections, and then validity would have to be determined
		// per-image, not per-task.
		imageData = this.images[ this.selectedImageIndex ];

	if ( !imageData ) {
		mw.log.error( 'Image suggestion list is empty' );
		return false;
	}

	const insertRange = this.getInsertRange( imageData );
	if ( !insertRange ) {
		// The error was already logged in getInsertRange().
		return false;
	}

	/** @type {ve.dm.Node[]} imageNodes */
	imageNodes.push( ...surfaceModel.getDocument().getNodesByType( 'mwBlockImage' ) );
	imageNodes.push( ...surfaceModel.getDocument().getNodesByType( 'mwInlineImage' ) );
	for ( let i = 0; i < imageNodes.length; i++ ) {
		if ( insertRange.containsOffset( imageNodes[ i ].getOffset() ) ) {
			mw.log.error( 'Section ' + imageData.sectionNumber + ' already contains an image: ' +
				imageNodes[ i ].getAttribute( 'resource' ) );
			return false;
		}
		const imageTitle = mw.Title.newFromText(
			// Parsoid filename attributes start with "./".
			imageNodes[ i ].getAttribute( 'resource' ).slice( 2 ),
		);
		if ( imageTitle.getMain() === imageData.image ) {
			mw.log.error( 'Image ' + imageData.image + ' is already used in another section' );
			return false;
		}
	}
	return true;
};

/**
 * Add a placeholder to indicate where the recommended image will be inserted.
 *
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 */
AddSectionImageArticleTarget.prototype.insertImagePlaceholder = function ( imageData ) {
	const dimensions = this.getImageDimensions( imageData );
	this.insertLinearModelAtRecommendationLocation( [
		{
			type: 'mwGeRecommendedImagePlaceholder',
			attributes: {
				width: dimensions.width,
				height: dimensions.height,
			},
		},
	], imageData );
};

/**
 * @inheritDoc
 */
AddSectionImageArticleTarget.prototype.replacePlaceholderWithImage = function ( imageData ) {
	const self = this,
		surfaceModel = this.getSurface().getModel();

	surfaceModel.setReadOnly( false );
	const recommendedImageNodes = surfaceModel.getDocument().getNodesByType( 'mwGeRecommendedImagePlaceholder' );
	recommendedImageNodes.forEach( ( node ) => {
		self.approvalTransaction = ve.dm.TransactionBuilder.static.newFromReplacement(
			surfaceModel.getDocument(),
			node.getOuterRange(),
			self.getImageLinearModel( imageData ),
			// doesn't matter but marginally faster
			true,
		);
		surfaceModel.change( self.approvalTransaction );
	} );
	surfaceModel.setReadOnly( true );
	this.hasStartedCaption = true;
};

/** @inheritDoc */
AddSectionImageArticleTarget.prototype.getInsertRange = function ( imageData ) {
	const surface = this.getSurface(),
		surfaceModel = surface.getModel(),
		surfaceView = surface.getView(),
		headingNodes = surfaceModel.getDocument().getNodesByType( 'mwHeading' );

	let heading, nextHeading;
	let h2Count = 0;
	for ( let i = 0; i < headingNodes.length; i++ ) {
		if ( headingNodes[ i ].getAttribute( 'level' ) !== 2 ) {
			// Currently only recommending for top-level headings.
			continue;
		}
		h2Count++;
		if ( !heading && this.isSameSection( headingNodes[ i ], h2Count, imageData ) ) {
			heading = headingNodes[ i ];
		} else if ( heading ) {
			nextHeading = headingNodes[ i ];
			break;
		}
	}

	// Set the target heading as a side effect.
	// FIXME refactor so the information flow is clearer; maybe move the linear model + DOM
	//   navigation logic to a helper class and have it convert imageData into an imageVeData
	//   object with range + header.
	if ( heading ) {
		this.targetHeading = surfaceView.getDocument().getDocumentNode().getNodeFromOffset( heading.getRange().start );
	}

	if ( nextHeading ) {
		return new ve.Range(
			heading.getOuterRange( false ).end,
			nextHeading.getOuterRange( false ).start,
		);
	} else if ( heading ) {
		return new ve.Range(
			heading.getOuterRange( false ).end,
			surfaceModel.getDocument().getDocumentRange().end,
		);
	} else {
		if ( h2Count < imageData.sectionNumber ) {
			mw.log.error( 'Section ' + imageData.sectionNumber + ' not found, the article only has ' +
				h2Count + ' h2 sections' );
		}
		return null;
	}
};

/**
 * Check if the given heading node is the section the recommendation was made for.
 *
 * @param {ve.dm.Node} node Section heading node.
 * @param {number} sectionNumber Section number. 1-based, only top-level (h2) sections are counted.
 * @param {mw.libs.ge.ImageRecommendationImage} imageData
 * @return {boolean}
 */
AddSectionImageArticleTarget.prototype.isSameSection = function ( node, sectionNumber, imageData ) {
	// FIXME accept null section numbers for now as the dataset hasn't been fully initialized yet.
	//   If the section number is null, we'll just try to match the text to every top-level section.
	if ( sectionNumber !== imageData.sectionNumber && imageData.sectionNumber !== null ) {
		return false;
	}

	// The article might have been edited since. Double-check that the title text matches.
	// imageData.sectionTitle is wikitext so this will be somewhat fragile.
	// The API format will change (T333333), so make sure the check works with old and new format.
	const expectedTitleText = imageData.sectionTitle.replace( /_/g, ' ' );
	const domElements = node.getOriginalDomElements( node.getStore() );
	const actualTitleText = $( '<div>' ).append( $( domElements ).clone() ).prop( 'innerText' );
	// Also compare with the HTML ID of the heading (after underscore conversion) as a fallback.
	// Note that the ID can have a numeric postfix like '_1' if there are multiple sections with
	// the same wikitext. This is rare enough that we just ignore it.
	const actualIdText = domElements[ 0 ] instanceof HTMLHeadingElement ?
		domElements[ 0 ].id.replace( /_/g, ' ' ) :
		'';

	if ( actualTitleText.toLowerCase() === expectedTitleText.toLowerCase() ||
		actualIdText && actualIdText.toLowerCase() === expectedTitleText.toLowerCase()
	) {
		// Not the most elegant way to pass this back, but we'll need it later - if the visible
		// title is significantly different from the wikitext title (e.g. due to LanguageConverter),
		// and we managed to match the section anyway with the fallabck mechanism, we should show
		// the user the title in the format in which it will be displayed in the article.
		// (There is a case to be made for even preserving basic formatting like italic here, but
		// that would be too complicated.)
		imageData.visibleSectionTitle = actualTitleText;
		return true;
	} else {
		mw.log.error(
			'Section title mismatch for section ' + imageData.sectionNumber + ': ' +
			'expected "' + imageData.sectionTitle + '", got "' + actualTitleText + '"',
		);
		return false;
	}
};

/**
 * Scroll the VE editor surface so that the title of the target section is at the top of the page.
 *
 * @return {jQuery.Promise}
 */
AddSectionImageArticleTarget.prototype.scrollToTargetSection = function () {
	this.suppressSurfaceSelectEvents( true );
	return OO.ui.Element.static.scrollIntoView( this.targetHeading.$element[ 0 ], {
		animate: true,
		alignToTop: true,
		duration: 'slow',
		padding: this.surface.padding,
		direction: 'y',
	} ).then( () => {
		this.suppressSurfaceSelectEvents( false );
	} );
};

/**
 * Disable (or reenable) effects from the 'select' event emitted by ve.dm.Surface being
 * handled by ve.ui.Surface. This is a hack to prevent the surface event handler from
 * interfering with programmatic scrolling.
 *
 * @param {boolean} suppress
 */
AddSectionImageArticleTarget.prototype.suppressSurfaceSelectEvents = function ( suppress ) {
	// It would be nicer to interfere at a less generic level closer to the 'select' event
	// triggering or handling, but there isn't an easy way to do that - part of the event
	// handling is debounced, so we might be dealing with scrolling that was initiated by an
	// event that fired before we started suppressing select events.
	if ( suppress ) {
		if ( !scrollingDeferred ) {
			scrollingDeferred = $.Deferred();
		}
		ve.scrollIntoView = function () {
			return scrollingDeferred.promise();
		};
	} else {
		ve.scrollIntoView = OO.ui.Element.static.scrollIntoView.bind( OO.ui.Element.static );
		if ( scrollingDeferred ) {
			scrollingDeferred.resolve();
			scrollingDeferred = null;
		}
	}
};

/**
 * Get data for the suggestion at the specified index (if any) or the selected suggestion
 * to pass to ImageSuggestionInteractionLogger.
 *
 * @param {number} [index] Zero-based index of the image suggestion for which to return data
 * @return {Object}
 */
AddSectionImageArticleTarget.prototype.getSuggestionLogActionData = function ( index ) {
	const imageIndex = typeof index === 'number' ? index : this.selectedImageIndex,
		imageData = this.images[ imageIndex ],
		sectionNumber = imageData.sectionNumber,
		sectionTitle = imageData.sectionTitle;

	const actionData = AddSectionImageArticleTarget.super.prototype.getSuggestionLogActionData.call( this, index );
	/* eslint-disable camelcase */
	actionData.section_ordinal = sectionNumber;
	actionData.section_title = sectionTitle;
	/* eslint-enable camelcase */
	return actionData;
};

/** @inheritDoc **/
AddSectionImageArticleTarget.prototype.formatSaveOptions = function ( saveOptions ) {
	// FIXME modify or remove this method override
	saveOptions.summary = '/* growthexperiments-addsectionimage-summary-summary: 1 */';
	return saveOptions;
};

module.exports = AddSectionImageArticleTarget;
