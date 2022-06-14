var StructuredTaskToolbarDialog = require( '../StructuredTaskToolbarDialog.js' ),
	DmRecommendedLinkAnnotation = require( './dmRecommendedLinkAnnotation.js' ),
	CeRecommendedLinkAnnotation = require( './ceRecommendedLinkAnnotation.js' ),
	AnnotationAnimation = require( '../AnnotationAnimation.js' ),
	CONSTANTS = require( 'ext.growthExperiments.DataStore' ).CONSTANTS,
	SUGGESTED_EDITS_CONFIG = CONSTANTS.SUGGESTED_EDITS_CONFIG,
	formatTitle = require( '../../utils/Utils.js' ).formatTitle,
	StructuredTaskPreEdit = require( 'ext.growthExperiments.StructuredTask.PreEdit' );

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialog
 * @extends mw.libs.ge.StructuredTaskToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialog() {
	RecommendedLinkToolbarDialog.super.apply( this, arguments );
	/**
	 * @property {Object[]} linkRecommendationFragments
	 */
	this.linkRecommendationFragments = [];
	/**
	 * @property {boolean} isUpdatingCurrentRecommendation Whether UI updates should be rendered
	 */
	this.isUpdatingCurrentRecommendation = true;
	/**
	 * @property {boolean} isFirstRender Whether the first recommendation is being rendered
	 */
	this.isFirstRender = true;
	/**
	 * @property {boolean} shouldSkipAutoAdvance Whether auto-advance should not occur
	 */
	this.shouldSkipAutoAdvance = false;
	/**
	 * @property {number} autoAdvanceDelay Delay in ms before auto-advancing
	 */
	this.autoAdvanceDelay = 600;
	/**
	 * @property {Object} extracts Article extracts that have been fetched
	 */
	this.extracts = {};
	this.$element.addClass( 'mw-ge-recommendedLinkToolbarDialog' );
}

OO.inheritClass( RecommendedLinkToolbarDialog, StructuredTaskToolbarDialog );

RecommendedLinkToolbarDialog.static.name = 'recommendedLink';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialog.prototype.initialize = function () {
	var introLabel = new OO.ui.LabelWidget( {
		label: mw.msg( 'growthexperiments-addlink-context-intro' ),
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-introLabel' ]
	} );
	RecommendedLinkToolbarDialog.super.prototype.initialize.call( this );

	// The following elements have to be set up when this.linkRecommendationFragments is set.
	this.$progress = $( '<span>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-progress' );
	this.$progressTitle = $( '<span>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-progress-title' );
	this.$buttons = $( '<div>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-buttons' );
	this.setupButtons();
	this.$head.append( [ this.getRobotIcon(), this.$progressTitle, this.$progress ] );
	this.$body.append( [ introLabel.$element, this.setupLinkPreview() ] );

	// Used by other dialogs to return focus.
	mw.hook( 'inspector-regainfocus' ).add( function () {
		this.regainFocus();
	}.bind( this ) );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return RecommendedLinkToolbarDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.surface = data.surface;
			this.linkRecommendationFragments = data.surface.linkRecommendationFragments;
			this.afterSetupProcess();
		}, this );
};

// Event Handlers

/**
 * Show the recommendation corresponding to the annotation model
 * This is used when the user clicks on an annotation instead of using
 * the navigation in the link inspector.
 *
 * @param {mw.libs.ge.dm.RecommendedLinkAnnotation|undefined} [annotationModel] DataModel
 * @param {boolean} [isActive] Whether the annotation is currently selected
 */
RecommendedLinkToolbarDialog.prototype.onAnnotationClicked = function (
	annotationModel,
	isActive
) {
	if ( !annotationModel ) {
		return;
	}

	// Only re-render if the content changes
	if ( !isActive || this.isHidden ) {
		this.showRecommendationAtIndex( this.getIndexForModel( annotationModel ), true );
	}
};

/**
 * Initialize elements after this.surface and this.linkRecommendationFragments are set
 */
RecommendedLinkToolbarDialog.prototype.afterSetupProcess = function () {
	this.updateSurfacePadding();
	this.setupProgressIndicators( this.linkRecommendationFragments.length );
	this.showFirstRecommendation().then( function () {
		this.updateActionButtonsMode();
		this.logger.log( 'impression', this.getSuggestionLogActionData() );
	}.bind( this ) );

	$( window ).on( 'resize',
		OO.ui.debounce( this.updateActionButtonsMode.bind( this ), 250 )
	);
	mw.hook( 'growthExperiments.onAnnotationClicked' ).add( this.onAnnotationClicked.bind( this ) );
};

/**
 * Show the previous suggestion if it exists
 * Do nothing if the user is on the first recommendation
 *
 * @param {boolean} [isSwipe] Whether the action is triggered via swipe gesture
 */
RecommendedLinkToolbarDialog.prototype.onPrevButtonClicked = function ( isSwipe ) {
	this.logger.log( 'back', $.extend(
		/* eslint-disable-next-line camelcase */
		this.getSuggestionLogActionData(), { navigation_type: isSwipe ? 'swipe' : 'click' }
	) );
	if ( this.currentIndex === 0 ) {
		return;
	}
	this.showRecommendationAtIndex( this.currentIndex - 1 );
};

/**
 * Show the next suggestion if it exists, if the user is on the last recommendation:
 * fire an event to save the article if user decided on any of the recommendations
 * show skipped all suggestions dialog if user didn't decide on any of the recommendations
 *
 * @param {boolean} [isSwipe] Whether the action is triggered via swipe gesture
 */
RecommendedLinkToolbarDialog.prototype.onNextButtonClicked = function ( isSwipe ) {
	this.logger.log( 'next', $.extend(
		/* eslint-disable-next-line camelcase */
		this.getSuggestionLogActionData(), { navigation_type: isSwipe ? 'swipe' : 'click' }
	) );
	if ( this.currentDataModel.isUndecided() ) {
		this.logger.log( 'suggestion_skip', this.getSuggestionLogActionData() );
	}
	if ( this.isLastRecommendationSelected() ) {
		if ( this.allRecommendationsSkipped() ) {
			if ( this.linkRecommendationFragments.length === 1 ) {
				this.goToSuggestedEdits();
			} else {
				this.showSkippedAllDialog();
			}
		} else {
			mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
		}
		return;
	}
	this.showRecommendationAtIndex( this.currentIndex + 1 );
};

/**
 * Accept the current recommendation if it hasn't been accepted
 * Mark it as undecided if it has been accepted
 */
RecommendedLinkToolbarDialog.prototype.onYesButtonClicked = function () {
	if ( this.currentDataModel.isRejected() ) {
		this.resetAcceptanceButtonStates();
	}
	if ( this.currentDataModel.isAccepted() ) {
		this.logger.log( 'suggestion_mark_undecided', $.extend( this.getSuggestionLogActionData(), {
			// eslint-disable-next-line camelcase
			previous_acceptance_state: 'accepted'
		} ) );
		this.setLastAnnotationState( true );
	} else {
		this.logger.log( 'suggestion_accept', this.getSuggestionLogActionData() );
		this.setLastAnnotationState();
	}
	this.setAccepted( this.currentDataModel.isAccepted() ? null : true );
};

/**
 * Reject the current recommendation if it hasn't been rejected
 * Mark it as undecided if it has been rejected
 */
RecommendedLinkToolbarDialog.prototype.onNoButtonClicked = function () {
	if ( this.currentDataModel.isAccepted() ) {
		this.resetAcceptanceButtonStates();
	}
	if ( this.currentDataModel.isRejected() ) {
		this.logger.log( 'suggestion_mark_undecided', $.extend( this.getSuggestionLogActionData(), {
			// eslint-disable-next-line camelcase
			previous_acceptance_state: 'rejected'
		} ) );
		this.setLastAnnotationState( true );
	} else {
		this.logger.log( 'suggestion_reject', this.getSuggestionLogActionData() );
		this.setLastAnnotationState();
	}
	this.setAccepted( this.currentDataModel.isRejected() ? null : false );
};

/**
 * Auto-advance after animation for the current recommendation is done
 */
RecommendedLinkToolbarDialog.prototype.autoAdvance = function () {
	var isLastRecommendationSelected = this.isLastRecommendationSelected();
	setTimeout( function () {
		if ( isLastRecommendationSelected ) {
			mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
		} else {
			this.showRecommendationAtIndex( this.currentIndex + 1 );
		}
	}.bind( this ), this.autoAdvanceDelay );
};

/**
 * Fire an event when a recommendation is accepted or rejected
 * This allows the publish button to be updated based on whether there are any acceptances.
 */
RecommendedLinkToolbarDialog.prototype.onAcceptanceChanged = function () {
	mw.hook( 'growthExperiments.suggestionAcceptanceChange' ).fire();
	this.updateButtonStates();
	this.updateActionButtonsMode();
	// Annotation element changes so it needs to be re-selected.
	this.selectAnnotationView();

	if ( this.shouldSkipAutoAdvance ) {
		return;
	}
	this.autoAdvance();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialog.prototype.teardown = function () {
	$( window ).off( 'resize' );
	// If an event is previously fired and the handler is added afterwards, the handler gets called
	// with the last argument. Fire this without passing the model in case the user navigates to
	// read mode and comes back since the handler has to be added again
	mw.hook( 'growthExperiments.onAnnotationClicked' ).fire();
	return RecommendedLinkToolbarDialog.super.prototype.teardown.apply( this, arguments );
};

// Interactions with annotation view & data model

/**
 * Reopen the rejection dialog.
 */
RecommendedLinkToolbarDialog.prototype.reopenRejectionDialog = function () {
	this.logger.log( 'suggestion_view_rejection_reasons', this.getSuggestionLogActionData() );
	this.setAccepted( false );
	this.shouldSkipAutoAdvance = true;
};

/**
 * Mark this suggestion as accepted, rejected or undecided, and store rejection reason if given.
 *
 * Commits a transaction that removes the existing annotation and adds a new one that is
 * identical except for the 'recommendationAccepted' attribute. This will cause the context item
 * to be destroyed, and a new one to be created for the new annotation.
 *
 * @param {boolean|null} accepted True if accepted, false if rejected, null if undecided
 * (the yes/no button has been toggled).
 */
RecommendedLinkToolbarDialog.prototype.setAccepted = function ( accepted ) {
	var acceptancePromise = $.Deferred().resolve(),
		fragment = this.getCurrentFragment(),
		annotation = this.getCurrentDataModel(),
		surfaceModel = this.surface.getModel(),
		oldReadOnly = surfaceModel.isReadOnly(),
		attributes = {
			recommendationAccepted: accepted,
			rejectionReason: undefined
		},
		getRejectionDialogLogActionData = function ( rejectionReason, otherReason ) {
			return $.extend( this.getSuggestionLogActionData(), {
				/* eslint-disable camelcase */
				rejection_reason: rejectionReason,
				other_reason: otherReason ? encodeURIComponent( otherReason ) : undefined
				/* eslint-enable camelcase */
			} );
		}.bind( this ),
		openRejectionDialogWindowPromise;

	// Temporarily disable read-only mode
	surfaceModel.setReadOnly( false );

	if ( accepted === false ) {
		// Update the annotation immediately, so that the acceptance state is correct for
		// impression and close events.
		this.updateAnnotation( fragment, annotation, attributes );

		openRejectionDialogWindowPromise = this.surface.dialogs.openWindow(
			'recommendedLinkRejection', {
				selection: this.currentDataModel.getRejectionReason(),
				otherRejectionReason: this.currentDataModel.getOtherRejectionReason()
			}
		);

		openRejectionDialogWindowPromise.opening.then( function () {
			this.logger.log(
				'impression',
				getRejectionDialogLogActionData(
					this.currentDataModel.getRejectionReason(),
					this.currentDataModel.getOtherRejectionReason()
				),
				// eslint-disable-next-line camelcase
				{ active_interface: 'rejection_dialog' }
			);
		}.bind( this ) );

		acceptancePromise = openRejectionDialogWindowPromise.closed.then( function ( closedData ) {
			var rejectionReason = closedData && closedData.reason ||
				this.currentDataModel.getRejectionReason();
			this.logger.log(
				'close',
				getRejectionDialogLogActionData( rejectionReason, closedData.otherRejectionReason ),
				// eslint-disable-next-line camelcase
				{ active_interface: 'rejection_dialog' }
			);
			return closedData;
		}.bind( this ) );
	}

	acceptancePromise.then( function ( closedData ) {
		closedData = closedData || {};

		var rejectionReason = closedData.reason,
			otherRejectionReason = closedData.otherRejectionReason;
		if ( rejectionReason ) {
			attributes.rejectionReason = rejectionReason;
		}

		if ( otherRejectionReason ) {
			attributes.otherRejectionReason = otherRejectionReason;
		}

		return this.updateAnnotation( fragment, annotation, attributes );
	}.bind( this ) ).then( function () {
		// Re-enable read-only mode (if it was previously enabled)
		surfaceModel.setReadOnly( oldReadOnly );
	} ).then( this.onAcceptanceChanged.bind( this ) );
};

/**
 * Get the index of the corresponding linkRecommendationFragment for the specified model
 *
 * @private
 * @param {mw.libs.ge.dm.RecommendedLinkAnnotation} annotationModel DataModel
 * @return {number} Zero-based index of the suggestion in the linkRecommendationFragments array
 */
RecommendedLinkToolbarDialog.prototype.getIndexForModel = function ( annotationModel ) {
	var modelRecommendationWikiTextOffset = annotationModel.getAttribute( 'recommendationWikitextOffset' ),
		currentIndex = this.currentIndex,
		fragment = this.linkRecommendationFragments[ currentIndex ],
		i, modelIndex;

	if ( modelRecommendationWikiTextOffset === fragment.recommendationWikitextOffset ) {
		return currentIndex;
	}

	for ( i = 0; i < this.linkRecommendationFragments.length; i++ ) {
		fragment = this.linkRecommendationFragments[ i ];
		if ( modelRecommendationWikiTextOffset === fragment.recommendationWikitextOffset ) {
			modelIndex = i;
			break;
		}
	}
	return typeof modelIndex !== 'undefined' ? modelIndex : currentIndex;
};

/**
 * Select the annotation view and maintain a reference to it so it can be used
 * to render the article text the recommended link is for
 */
RecommendedLinkToolbarDialog.prototype.selectAnnotationView = function () {
	this.surface.getView().selectAnnotation( function ( annotationView ) {
		if ( annotationView instanceof CeRecommendedLinkAnnotation ) {
			this.annotationView = annotationView;
			this.annotationView.updateActiveClass( true );
			return true;
		}
	}.bind( this ) );
	this.regainFocus();
};

/**
 * Select the fragment and show the content specific to the recommendation at the specified index
 * Do nothing if the recommendation has already been shown and this.isUpdatingCurrentRecommendation
 * is false, or if the index is invalid
 *
 * @param {number} index Zero-based index of the suggestion in the linkRecommendationFragments array
 * @param {boolean} [manualFocus] if the recommendation was manually focused via a click/tap as
 *  opposed to using the next/back buttons.
 * @throws Will throw an error if this.linkRecommendationFragments is empty
 */
RecommendedLinkToolbarDialog.prototype.showRecommendationAtIndex = function (
	index, manualFocus
) {
	var isUpdatingCurrentRecommendation = this.isUpdatingCurrentRecommendation;
	if ( !isUpdatingCurrentRecommendation && ( index === this.currentIndex || index < 0 ) ) {
		return;
	}
	if ( this.linkRecommendationFragments.length === 0 ) {
		throw new Error( 'No link recommendation fragments' );
	}
	this.isGoingBack = this.currentIndex > index;
	this.linkRecommendationFragments[ index ].fragment.select();
	this.currentIndex = index;
	this.currentDataModel = this.getCurrentDataModel();
	this.isUpdatingCurrentRecommendation = false;
	// Before selecting the new annotation view, unset active state on the current one
	if ( this.annotationView ) {
		this.annotationView.updateActiveClass( false );
	}
	this.selectAnnotationView();
	this.updateContentForCurrentRecommendation();
	this.logger.log(
		'suggestion_focus',
		// eslint-disable-next-line camelcase
		$.extend( this.getSuggestionLogActionData(), { manual_focus: manualFocus || false } )
	);
};

/**
 * Scroll to the first annotation view and show the corresponding link inspector
 *
 * @return {jQuery.Promise} Promise which resolves when the link inspector is shown
 */
RecommendedLinkToolbarDialog.prototype.showFirstRecommendation = function () {
	var promise = $.Deferred(),
		annotationView = this.getAnnotationViewAtIndex( 0 );
	if ( !annotationView ) {
		this.toggle( false );
		return promise.reject();
	}
	this.scrollToAnnotationView( annotationView ).then( function () {
		this.showRecommendationAtIndex( 0 );
		this.$element.removeClass( 'animate-below' );
		promise.resolve();
	}.bind( this ) );
	return promise;
};

/**
 * Get annotation view for the specified index
 *
 * @param {number} index Zero-based index of the suggestion in the linkRecommendationFragments array
 * @throws Will throw an error if annotation view at the specified index can't be found
 * @return {jQuery}
 */
RecommendedLinkToolbarDialog.prototype.getAnnotationViewAtIndex = function ( index ) {
	var annotationView = this.surface.getView().$documentNode
		.find( '.mw-ge-recommendedLinkAnnotation' )[ index ];
	if ( !annotationView ) {
		StructuredTaskPreEdit.showErrorDialogOnFailure( 'Unable to find any expected phrase in document.' );
	}
	return annotationView;
};

/**
 * Scroll so that the specified annotation view is in the viewport
 *
 * @param {jQuery} $el Annotation view to scroll to
 * @return {jQuery.Promise} Promise which resolves when the scroll is complete or
 * when scrollTimeout is reached
 */
RecommendedLinkToolbarDialog.prototype.scrollToAnnotationView = function ( $el ) {
	var promise = $.Deferred(),
		resolveTimeout = setTimeout( function () {
			promise.resolve();
		}, this.scrollTimeout );
	OO.ui.Element.static.scrollIntoView( $el, {
		animate: true,
		duration: 'slow',
		padding: this.surface.padding,
		direction: 'y'
	} ).then( function () {
		clearTimeout( resolveTimeout );
		promise.resolve();
	} );
	return promise;
};

/**
 * Get the DataModel surface fragment of the current recommendation
 *
 * @private
 * @return {ve.dm.SurfaceFragment}
 *
 * @throws Will throw an error if there is no link recommendation
 */
RecommendedLinkToolbarDialog.prototype.getCurrentFragment = function () {
	var currentRecommendation = this.linkRecommendationFragments[ this.currentIndex ];
	if ( !currentRecommendation ) {
		throw new Error( 'No recommendation' );
	}
	return currentRecommendation.fragment;
};

/**
 * Get the DataModel of the current recommendation's annotation
 *
 * @private
 * @return {mw.libs.ge.dm.RecommendedLinkAnnotation}
 */
RecommendedLinkToolbarDialog.prototype.getCurrentDataModel = function () {
	var selectedAnnotations = this.getCurrentFragment().getAnnotations();
	return selectedAnnotations.getAnnotationsByName( 'mwGeRecommendedLink' ).get( 0 );
};

/**
 * Check whether all recommendations were skipped
 *
 * @private
 * @return {boolean}
 */
RecommendedLinkToolbarDialog.prototype.allRecommendationsSkipped = function () {
	return this.linkRecommendationFragments.every( function ( recommendation ) {
		var annotationSet = recommendation.fragment
			.getAnnotations().getAnnotationsByName( 'mwGeRecommendedLink' );
		return annotationSet.getLength() ? annotationSet.get( 0 ).isUndecided() : false;
	} );
};

// UI setup methods for elements that are not specific to the current recommendation

/**
 * Set up yes, no and reopen rejection dialog buttons
 *
 * @return {jQuery}
 */
RecommendedLinkToolbarDialog.prototype.setupAcceptanceButtons = function () {
	this.yesButton = new OO.ui.ToggleButtonWidget( {
		icon: 'check',
		label: mw.msg( 'growthexperiments-addlink-context-button-accept' ),
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-buttons-yes' ]
	} );
	this.noButton = new OO.ui.ToggleButtonWidget( {
		icon: 'close',
		label: mw.msg( 'growthexperiments-addlink-context-button-reject' ),
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-buttons-no' ]
	} );
	this.reopenRejectionDialogButton = new OO.ui.ButtonWidget( {
		icon: 'ellipsis',
		framed: false,
		title: mw.msg( 'growthexperiments-addlink-rejectiondialog-reopen-button-title' ),
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-buttons-no-reopen' ]
	} );
	this.$acceptanceButtonGroup = $( '<div>' ).addClass(
		'mw-ge-recommendedLinkToolbarDialog-buttons-acceptance-group'
	);
	this.$acceptanceButtonGroup.append( [
		this.yesButton.$element,
		this.noButton.$element,
		this.reopenRejectionDialogButton.$element
	] );
	this.yesButton.connect( this, { click: [ 'onYesButtonClicked' ] } );
	this.noButton.connect( this, { click: [ 'onNoButtonClicked' ] } );
	this.reopenRejectionDialogButton.connect( this, { click: [ 'reopenRejectionDialog', null ] } );
	return this.$acceptanceButtonGroup;
};

/**
 * Set up navigation and acceptance buttons
 *
 * @return {jQuery}
 */
RecommendedLinkToolbarDialog.prototype.setupButtons = function () {
	this.prevButton = new OO.ui.ButtonWidget( {
		icon: 'previous',
		framed: false,
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-buttons-prev' ]
	} );
	this.nextButton = new OO.ui.ButtonWidget( {
		icon: 'next',
		framed: false,
		label: mw.msg( 'growthexperiments-addlink-context-button-next' ),
		classes: [ 'mw-ge-recommendedLinkToolbarDialog-buttons-next' ]
	} );
	this.prevButton.connect( this, { click: [ 'onPrevButtonClicked' ] } );
	this.nextButton.connect( this, { click: [ 'onNextButtonClicked' ] } );
	this.$navButtonGroup = $( '<div>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-buttons-nav-group' );
	this.$navButtonGroup.append( [ this.prevButton.$element, this.nextButton.$element ] );
	this.$buttons.append( this.setupAcceptanceButtons(), this.$navButtonGroup );
	return this.$buttons;
};

/**
 * Build a series of progress indicator spans
 *
 * @private
 * @param {number} total Total number of indicators to build
 */
RecommendedLinkToolbarDialog.prototype.setupProgressIndicators = function ( total ) {
	var i, $indicator;

	for ( i = 0; i < total; i++ ) {
		$indicator = $( '<span>' ).addClass( 'mw-ge-recommendedLinkToolbarDialog-progress-indicator' );
		this.$progress.append( $indicator );
	}
};

/**
 * Set up link preview element
 *
 * @return {jQuery}
 */
RecommendedLinkToolbarDialog.prototype.setupLinkPreview = function () {
	this.$linkPreview = $( '<div>' ).addClass(
		'mw-ge-recommendedLinkToolbarDialog-linkPreview'
	);
	return this.$linkPreview;
};

// UI update methods based on recommendation-specific "computed properties"

/**
 * Construct link element for link preview
 *
 * This logic is the same as in ve.ui.MWInternalLinkContextItem.static.generateBody.
 *
 * @return {jQuery}
 */
RecommendedLinkToolbarDialog.prototype.getLinkForPreview = function () {
	var linkCache = ve.init.platform.linkCache,
		title = this.currentDataModel.getAttribute( 'lookupTitle' ),
		normalizedTitle = this.currentDataModel.getAttribute( 'normalizedTitle' ),
		href = this.currentDataModel.getHref(),
		titleObj = mw.Title.newFromText( mw.libs.ve.normalizeParsoidResourceName( href ) ),
		$link = $( '<a>' )
			.addClass( 'mw-ge-recommendedLinkToolbarDialog-linkPreview-link' )
			.text( normalizedTitle )
			.attr( {
				href: titleObj.getUrl(),
				target: '_blank',
				rel: 'noopener'
			} );
	linkCache.styleElement( title, $link, this.currentDataModel.getFragment() );
	$link.removeClass( 'mw-selflink' );
	$link.on( 'click', function () {
		this.logger.log( 'link_click', this.getSuggestionLogActionData() );
	}.bind( this ) );
	return $link;
};

/**
 * Construct link preview element
 *
 * @return {jQuery}
 */
RecommendedLinkToolbarDialog.prototype.getLinkPreview = function () {
	var icon = new OO.ui.IconWidget( {
			icon: 'image',
			classes: [ 'mw-ge-recommendedLinkToolbarDialog-linkPreview-icon' ]
		} ),
		$extract = $( '<span>' ).addClass(
			'mw-ge-recommendedLinkToolbarDialog-linkPreview-extract'
		),
		$linkPreviewBody = $( '<div>' ).addClass(
			'mw-ge-recommendedLinkToolbarDialog-linkPreview-body'
		);

	ve.init.platform.linkCache.get( this.currentDataModel.getAttribute( 'lookupTitle' ) )
		.then( function ( linkData ) {
			if ( linkData.imageUrl ) {
				icon.$element.addClass( 'mw-ge-recommendedLinkToolbarDialog-linkPreview-hasImage' )
					.css( 'background-image', 'url(' + linkData.imageUrl + ')' );
			}
		} );

	$linkPreviewBody.append( [
		$( '<div>' )
			.addClass( 'mw-ge-recommendedLinkToolbarDialog-linkPreview-thumbnail' )
			.append( icon.$element ),
		$( '<div>' )
			.addClass( 'mw-ge-recommendedLinkToolbarDialog-linkPreview-content' )
			.append( [ this.getLinkForPreview(), $extract ] )
	] );

	this.fetchArticleExtract().then( function ( extract ) {
		$extract.text( extract );
	} );

	return $linkPreviewBody;
};

/**
 * Render content specific to the current recommendation
 *
 * @throws Will throw an error if there's no DataModel
 */
RecommendedLinkToolbarDialog.prototype.updateContentForCurrentRecommendation = function () {
	if ( !this.currentDataModel ) {
		throw new Error( 'No DataModel' );
	}
	this.$linkPreview.html( this.getLinkPreview() );
	this.updateButtonStates();
	this.updateProgressIndicators();
};

/**
 * Update button states based on the current DataModel
 */
RecommendedLinkToolbarDialog.prototype.updateButtonStates = function () {
	this.prevButton.setDisabled( this.currentIndex === 0 );
	this.yesButton.setValue( this.currentDataModel.isAccepted() );
	this.noButton.setValue( this.currentDataModel.isRejected() );
	this.reopenRejectionDialogButton.toggle( this.currentDataModel.isRejected() );
};

/**
 * Reset active states on accept, reject and reopen rejection dialog buttons
 */
RecommendedLinkToolbarDialog.prototype.resetAcceptanceButtonStates = function () {
	this.yesButton.setValue( false );
	this.noButton.setValue( false );
	this.reopenRejectionDialogButton.toggle( false );
};

/**
 * Update states of progress indicator dots based on current progress
 */
RecommendedLinkToolbarDialog.prototype.updateProgressIndicators = function () {
	var currentIndex = this.currentIndex;
	this.$progress.children().each( function ( index, indicator ) {
		if ( index <= currentIndex ) {
			indicator.classList.add( 'mw-ge-recommendedLinkToolbarDialog-progress-indicator-selected' );
		} else {
			indicator.classList.remove( 'mw-ge-recommendedLinkToolbarDialog-progress-indicator-selected' );
		}
	} );
	this.$progressTitle.text(
		mw.msg(
			'growthexperiments-addlink-context-title',
			mw.language.convertNumber( this.currentIndex + 1 ),
			mw.language.convertNumber( this.linkRecommendationFragments.length )
		)
	);
};

/**
 * Update action buttons' classes based on whether yes, no, and rejection dialog
 * buttons can be shown on the same line as prev & next buttons
 *
 * There are 3 possible states:
 * - Acceptance and navigation buttons on the same line, acceptance buttons aligned with description
 * - Acceptance buttons separated, aligned with description
 * - Acceptance buttons separated, center aligned (overflow if aligned w/description)
 */
RecommendedLinkToolbarDialog.prototype.updateActionButtonsMode = function () {
	var acceptanceButtonsWidth = this.acceptanceButtonsWidth || this.$acceptanceButtonGroup.width(),
		$nextButton = this.nextButton.$element,
		nextButtonLeft = $nextButton.position().left,
		$linkPreviewText = this.$linkPreview.find(
			'.mw-ge-recommendedLinkToolbarDialog-linkPreview-content'
		),
		linkPreviewTextLeft = this.linkPreviewTextLeft || $linkPreviewText.position().left,
		canOverflowStateAlign = false,
		imageOffset = 68, // @imageThumbnailSize + @gutterSize in RecommendedLinkToolbarDialog.less
		availableWidth;

	// This doesn't have to be re-computed (doesn't change upon window resize).
	this.acceptanceButtonsWidth = acceptanceButtonsWidth;
	this.linkPreviewTextLeft = linkPreviewTextLeft;

	if ( this.reopenRejectionDialogButton.isVisible() ) {
		acceptanceButtonsWidth += this.reopenRejectionDialogButton.$element.outerWidth();
	}

	// Use document dir rather than surface dir since this corresponds to the direction of the UI
	if ( document.documentElement.dir === 'rtl' ) {
		availableWidth = $linkPreviewText.width() - $nextButton.width();
	} else {
		availableWidth = nextButtonLeft - linkPreviewTextLeft;
	}

	if ( availableWidth < acceptanceButtonsWidth ) {
		canOverflowStateAlign = acceptanceButtonsWidth + imageOffset < this.$buttons.width();
		this.$acceptanceButtonGroup.addClass( 'overflow-state' );
		this.$acceptanceButtonGroup.toggleClass( 'overflow-state-left-aligned', canOverflowStateAlign );
		// Push nav buttons down onto its own line
		this.$navButtonGroup.css( 'margin-top', this.$acceptanceButtonGroup.outerHeight( true ) );
	} else {
		this.$acceptanceButtonGroup.removeClass( 'overflow-state' );
		this.$navButtonGroup.css( 'margin-top', 0 );
	}

	this.updateDimensions();
};

/**
 * Update the bottom padding value on the surface so that
 * scroll-into-view calculations can be adjusted so that both
 * the annotation and the link inspector are in the viewport
 */
RecommendedLinkToolbarDialog.prototype.updateSurfacePadding = function () {
	var bottomPadding = Math.max( this.$element.height(), this.minHeight ) + this.scrollOffset,
		topOffset = this.topOffset || 0,
		topPadding;
	if ( !this.originalTopPadding ) {
		this.originalTopPadding = this.surface.padding.top;
	}
	topPadding = this.originalTopPadding + topOffset;
	this.surface.setPadding( { top: topPadding, bottom: bottomPadding } );
};

/**
 * Update the window size and surface padding in case the window height changes
 */
RecommendedLinkToolbarDialog.prototype.updateDimensions = function () {
	this.updateSize();
	this.updateSurfacePadding();
};

/**
 * Show a dialog informing the user that they skipped all recommendations and
 * offering them to stay or leave.
 */
RecommendedLinkToolbarDialog.prototype.showSkippedAllDialog = function () {
	// eslint-disable-next-line camelcase
	var logMetadata = { active_interface: 'skipall_dialog' };
	this.logger.log( 'impression', {}, logMetadata );
	this.surface.dialogs.openWindow( 'structuredTaskMessage', {
		title: mw.message( 'growthexperiments-addlink-skip-title' ).text(),
		message: mw.message( 'growthexperiments-addlink-skip-body' ).text(),
		actions: [
			{
				action: 'accept',
				label: mw.message( 'growthexperiments-addlink-skip-accept' ).text()
			},
			{
				action: 'reject',
				label: mw.message( 'growthexperiments-addlink-skip-reject' ).text()
			}
		]
	} ).closed.then( function ( data ) {
		if ( data && data.action === 'accept' ) {
			this.logger.log( 'confirm_skip_all_suggestions', {}, logMetadata );
			this.goToSuggestedEdits();
		} else {
			this.logger.log( 'review_again', {}, logMetadata );
			this.showRecommendationAtIndex( 0 );
			this.regainFocus();
		}
	}.bind( this ) );
};

// Helpers

/**
 * Clear an annotation for a fragment, and set a new one with the provided attributes.
 *
 * @param {ve.dm.SurfaceFragment} fragment
 * @param {mw.libs.ge.dm.RecommendedLinkAnnotation} annotation
 * @param {Object} attributes
 */
RecommendedLinkToolbarDialog.prototype.updateAnnotation = function (
	fragment, annotation, attributes
) {
	fragment.annotateContent( 'clear', annotation );
	this.isUpdatingCurrentRecommendation = true;
	fragment.annotateContent( 'set', new DmRecommendedLinkAnnotation( $.extend( true,
		annotation.getElement(),
		{ attributes: attributes }
	) ) );
};

/**
 * Store the last annotation state before the annotation is updated
 * This is used to animate the acceptance state icons in the annotation view.
 * The annotation views for the corresponding paragraph are re-rendered when the data changes
 * (not just the annotation that's being cleared) so the state needs to be stored in a
 * singleton so that animation can occur only on the annotation view that's being updated.
 *
 * @param {boolean|undefined} isDeselect Whether the annotation state is being un-applied
 */
RecommendedLinkToolbarDialog.prototype.setLastAnnotationState = function ( isDeselect ) {
	var annotation = this.getCurrentDataModel();
	AnnotationAnimation.setLastState( {
		oldState: annotation.getState(),
		recommendationWikitextOffset: annotation.getAttribute( 'recommendationWikitextOffset' ),
		isDeselect: isDeselect
	} );
	this.shouldSkipAutoAdvance = isDeselect;
};

/**
 * Check whether the last recommendation is shown
 *
 * @return {boolean}
 */
RecommendedLinkToolbarDialog.prototype.isLastRecommendationSelected = function () {
	return this.currentIndex === this.linkRecommendationFragments.length - 1;
};

/**
 * Get action data to pass to the LinkSuggestionInteractionLogger.
 *
 * @override
 * @return {{
 * acceptance_state: string,
 * probability_score: number,
 * link_target: string,
 * series_number: number,
 * rejection_reason: (null|string),
 * link_text: string
 * }}
 */
RecommendedLinkToolbarDialog.prototype.getSuggestionLogActionData = function () {
	var suggestion = this.getCurrentDataModel().element.attributes,
		acceptanceState = 'undecided';
	if ( this.getCurrentDataModel().isAccepted() ) {
		acceptanceState = 'accepted';
	} else if ( this.getCurrentDataModel().isRejected() ) {
		acceptanceState = 'rejected';
	}

	return {
		/* eslint-disable camelcase */
		link_target: suggestion.normalizedTitle,
		link_text: suggestion.text,
		probability_score: suggestion.score,
		series_number: this.getIndexForModel( this.currentDataModel ),
		rejection_reason: this.currentDataModel.isRejected() ? this.currentDataModel.getRejectionReason() : '',
		acceptance_state: acceptanceState
		/* eslint-enable camelcase */
	};
};

/**
 * Fetch article extract for the selected suggestion
 *
 * @return {jQuery.Promise} Promise that resolves when the extract has been fetched
 */
RecommendedLinkToolbarDialog.prototype.fetchArticleExtract = function () {
	var promise = $.Deferred(),
		apiUrlBase = SUGGESTED_EDITS_CONFIG.GERestbaseUrl,
		title = this.currentDataModel.getAttribute( 'lookupTitle' );

	if ( this.extracts[ title ] ) {
		promise.resolve( this.extracts[ title ] );
		return promise;
	}

	if ( !apiUrlBase ) {
		promise.reject();
		return promise;
	}

	$.get( apiUrlBase + '/page/summary/' + formatTitle( title ) ).then( function ( data ) {
		if ( data && data.extract ) {
			this.extracts[ title ] = data.extract;
			promise.resolve( data.extract );
		} else {
			promise.reject();
		}
	}.bind( this ) ).catch( function () {
		promise.reject();
	} );
	return promise;
};

module.exports = RecommendedLinkToolbarDialog;
