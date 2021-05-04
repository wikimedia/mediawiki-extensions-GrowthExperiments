var DmRecommendedLinkAnnotation = require( './dmRecommendedLinkAnnotation.js' ),
	CeRecommendedLinkAnnotation = require( './ceRecommendedLinkAnnotation.js' );

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialog
 * @extends ve.ui.ToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialog() {
	RecommendedLinkToolbarDialog.super.apply( this, arguments );
	/**
	 * @property {Object[]} linkRecommendationFragments
	 */
	this.linkRecommendationFragments = [];
	/**
	 * @property {number} currentIndex Zero-based index of the selected recommendation
	 */
	this.currentIndex = 0;
	/**
	 * @property {ve.ui.Surface} surface VisualEditor UI surface
	 */
	this.surface = null;
	/**
	 * @property {boolean} isUpdatingCurrentRecommendation Whether UI updates should be rendered
	 */
	this.isUpdatingCurrentRecommendation = true;
	/**
	 * @property {Function} onContextChangeDebounced Debounced handler for onContextChange event
	 */
	this.onContextChangeDebounced = ve.debounce(
		this.showRecommendationForSelection.bind( this ),
		250
	);
	/**
	 * @property {number} scrollOffset Amount of space between the window and the annotation when scrolled
	 */
	this.scrollOffset = 100;
	/**
	 * @property {number} minHeight Minimum value to use for window height (used in setting surface padding value
	 */
	this.minHeight = 250;
	this.$element.addClass( 'mw-ge-recommendedLinkContextItem' );
}

OO.inheritClass( RecommendedLinkToolbarDialog, ve.ui.ToolbarDialog );

RecommendedLinkToolbarDialog.static.name = 'recommendedLink';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialog.prototype.initialize = function () {
	var introLabel = new OO.ui.LabelWidget( {
			label: mw.msg( 'growthexperiments-addlink-context-intro' ),
			classes: [ 'mw-ge-recommendedLinkContextItem-introLabel' ]
		} ),
		robotIcon = new OO.ui.IconWidget( {
			icon: 'robot',
			label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-machine-description' ),
			invisibleLabel: true,
			classes: [ 'mw-ge-recommendedLinkContextItem-head-robot-icon' ]
		} );
	RecommendedLinkToolbarDialog.super.prototype.initialize.call( this );
	this.$buttons = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons' );
	this.setupButtons();

	// The following elements have to be set up when this.linkRecommendationFragments is set.
	this.$progress = $( '<span>' ).addClass( 'mw-ge-recommendedLinkContextItem-progress' );
	this.$linkPreview = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-linkPreview' );
	this.$progressTitle = $( '<span>' ).addClass( 'mw-ge-recommendedLinkContextItem-progress-title' );
	this.$head.append( [ robotIcon.$element, this.$progressTitle, this.$progress ] );
	this.$body.append( [ introLabel.$element, this.$linkPreview, this.$buttons ] );
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
			this.surface.getModel().connect( this, { contextChange: 'onContextChange' } );
			this.afterSetupProcess();
		}, this );
};

// Event Handlers

/**
 * Initialize elements after this.surface and this.linkRecommendationFragments are set
 */
RecommendedLinkToolbarDialog.prototype.afterSetupProcess = function () {
	this.updateSurfacePadding();
	this.setupProgressIndicators( this.linkRecommendationFragments.length );
	this.showRecommendationAtIndex( this.currentIndex );
	setTimeout( this.updateActionButtonsMode.bind( this ) );
	$( window ).on( 'resize',
		OO.ui.debounce( this.updateActionButtonsMode.bind( this ), 250 )
	);
	// By default, a box is shown on top of the selected annotation.
	this.$surfaceSelectionOverlay = this.surface.$element.find( '.ve-ce-surface-deactivatedSelection-showAsDeactivated' );
	this.$surfaceSelectionOverlay.hide();
	/**
	 * HACK: Customize getIconForLink function which gets called in generateBody
	 * Even though linkCache is passed as the first argument, getIconForLink is called directly
	 * from ve.init.platform.linkCache.constructor so passing a custom linkCache object won't work.
	 * Store the original implementation to be restored
	 */
	this.originalGetIconForLink = ve.init.platform.linkCache.constructor.static.getIconForLink;
	this.setLinkCacheIconFunction( function () {
		return 'image';
	} );
};

/**
 * Show the previous suggestion if it exists
 * Do nothing if the user is on the first recommendation
 */
RecommendedLinkToolbarDialog.prototype.onPrevButtonClicked = function () {
	if ( this.currentIndex === 0 ) {
		return;
	}
	this.showRecommendationAtIndex( this.currentIndex - 1 );
};

/**
 * Show the next suggestion if it exists, if the user is on the last recommendation:
 * fire an event to save the article if user decided on any of the recommendations
 * show skipped all suggestions dialog if user didn't decide on any of the recommendations
 */
RecommendedLinkToolbarDialog.prototype.onNextButtonClicked = function () {
	if ( this.isLastRecommendationSelected() ) {
		if ( this.allRecommendationsSkipped() ) {
			this.showSkippedAllDialog();
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
	this.setAccepted( this.currentDataModel.isAccepted() ? null : true );
};

/**
 * Reject the current recommendation if it hasn't been rejected
 * Mark it as undecided if it has been rejected
 */
RecommendedLinkToolbarDialog.prototype.onNoButtonClicked = function () {
	this.setAccepted( this.currentDataModel.isRejected() ? null : false );
};

/**
 * Fire an event when a recommendation is accepted or rejected
 * This allows the publish button to be updated based on whether there are any acceptances.
 */
RecommendedLinkToolbarDialog.prototype.onAcceptanceChanged = function () {
	var linkRecommendationFragments = this.linkRecommendationFragments,
		hasAcceptedRecommendations = linkRecommendationFragments.some( function ( recommendation ) {
			var annotationSet = recommendation.fragment
				.getAnnotations().getAnnotationsByName( 'mwGeRecommendedLink' );
			return annotationSet.getLength() ? annotationSet.get( 0 ).isAccepted() : false;
		} );
	mw.hook( 'growthExperiments.linkSuggestionAcceptanceChange' ).fire( hasAcceptedRecommendations );
};

/**
 * Handle context change events from the surface model
 */
RecommendedLinkToolbarDialog.prototype.onContextChange = function () {
	this.onContextChangeDebounced();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialog.prototype.teardown = function () {
	$( window ).off( 'resize' );
	this.setLinkCacheIconFunction( this.originalGetIconForLink );
	this.$surfaceSelectionOverlay.show();
	return RecommendedLinkToolbarDialog.super.prototype.teardown.apply( this, arguments );
};

// Interactions with annotation view & data model

/**
 * Show the recommendation corresponding to the current selection
 * This is used when the user clicks on an annotation instead of using
 * the navigation in the link inspector.
 */
RecommendedLinkToolbarDialog.prototype.showRecommendationForSelection = function () {
	this.showRecommendationAtIndex( this.getIndexForCurrentSelection() );
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
	var acceptancePromise,
		fragment = this.getCurrentFragment(),
		annotation = this.getCurrentDataModel(),
		surfaceModel = this.surface.getModel(),
		oldReadOnly = surfaceModel.isReadOnly(),
		attributes = {
			recommendationAccepted: accepted,
			rejectionReason: undefined
		};

	this.resetAcceptanceButtonStates();
	// Temporarily disable read-only mode
	surfaceModel.setReadOnly( false );

	if ( accepted || accepted === null ) {
		acceptancePromise = $.Deferred().resolve();
	}
	if ( accepted === false ) {
		acceptancePromise = this.surface.dialogs
			.openWindow( 'recommendedLinkRejection', this.currentDataModel.getRejectionReason() ).closed
			.then( function ( closedData ) {
				return closedData && closedData.reason ||
					this.currentDataModel.getRejectionReason();
			}.bind( this ) );
	}

	acceptancePromise.then( function ( rejectionReason ) {
		if ( rejectionReason ) {
			attributes.rejectionReason = rejectionReason;
		}
		fragment.annotateContent( 'clear', annotation );
		this.isUpdatingCurrentRecommendation = true;
		fragment.annotateContent( 'set', new DmRecommendedLinkAnnotation( $.extend( true,
			annotation.getElement(),
			{ attributes: attributes }
		) ) );
	}.bind( this ) ).then( function () {
		// Re-enable read-only mode (if it was previously enabled)
		surfaceModel.setReadOnly( oldReadOnly );
	} ).then( this.onAcceptanceChanged.bind( this ) );
};

/**
 * Get the index of the corresponding linkRecommendationFragment for the current selection
 *
 * @private
 * @return {number} Zero-based index of the suggestion in the linkRecommendationFragments array
 */
RecommendedLinkToolbarDialog.prototype.getIndexForCurrentSelection = function () {
	var currentSelection = this.surface.getModel().getSelection(),
		currentSelectionRange = currentSelection.range,
		currentIndex = this.currentIndex,
		currentRange = this.linkRecommendationFragments[ currentIndex ].fragment.selection.range,
		i, currentSelectionIndex,
		recommendation, recommendationSelectionRange;

	if ( !currentSelectionRange || currentRange.containsRange( currentSelectionRange ) ) {
		return currentIndex;
	}

	for ( i = 0; i < this.linkRecommendationFragments.length; i++ ) {
		recommendation = this.linkRecommendationFragments[ i ].fragment;
		recommendationSelectionRange = recommendation.selection.range;
		if ( i !== currentIndex &&
			recommendationSelectionRange.containsRange( currentSelectionRange ) ) {
			currentSelectionIndex = i;
			break;
		}
	}
	// currentSelectionIndex is undefined when the user selects outside the annotations.
	// Since the context item remains open, show the last selected recommendation.
	return typeof currentSelectionIndex !== 'undefined' ? currentSelectionIndex : currentIndex;
};

/**
 * Select the annotation view and maintain a reference to it so it can be used
 * to render the article text the recommended link is for
 */
RecommendedLinkToolbarDialog.prototype.selectAnnotationView = function () {
	this.surface.getView().selectAnnotation( function ( annotationView ) {
		if ( annotationView instanceof CeRecommendedLinkAnnotation ) {
			this.annotationView = annotationView;
			return true;
		}
	}.bind( this ) );
};

/**
 * Select the fragment and show the content specific to the recommendation at the specified index
 * Do nothing if the recommendation has already been shown and this.isUpdatingCurrentRecommendation
 * is false, or if the index is invalid
 *
 * @param {number} index Zero-based index of the suggestion in the linkRecommendationFragments array
 * @throws Will throw an error if this.linkRecommendationFragments is empty
 */
RecommendedLinkToolbarDialog.prototype.showRecommendationAtIndex = function ( index ) {
	var isUpdatingCurrentRecommendation = this.isUpdatingCurrentRecommendation;
	if ( !isUpdatingCurrentRecommendation && ( index === this.currentIndex || index < 0 ) ) {
		return;
	}
	if ( this.linkRecommendationFragments.length === 0 ) {
		throw new Error( 'No link recommendation fragments' );
	}
	this.linkRecommendationFragments[ index ].fragment.select();
	this.currentIndex = index;
	this.currentDataModel = this.getCurrentDataModel();
	this.isUpdatingCurrentRecommendation = false;
	this.selectAnnotationView();
	this.updateContentForCurrentRecommendation();
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
 * Set up navigation and acceptance buttons
 *
 * @private
 */
RecommendedLinkToolbarDialog.prototype.setupButtons = function () {
	this.prevButton = new OO.ui.ButtonWidget( {
		icon: 'previous',
		framed: false,
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-prev' ]
	} );
	this.nextButton = new OO.ui.ButtonWidget( {
		icon: 'next',
		framed: false,
		label: mw.msg( 'growthexperiments-addlink-context-button-next' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-next' ]
	} );
	this.prevButton.connect( this, { click: [ 'onPrevButtonClicked' ] } );
	this.nextButton.connect( this, { click: [ 'onNextButtonClicked' ] } );
	this.$navButtonGroup = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons-nav-group' );
	this.$navButtonGroup.append( [ this.prevButton.$element, this.nextButton.$element ] );
	this.yesButton = new OO.ui.ToggleButtonWidget( {
		icon: 'check',
		label: mw.msg( 'growthexperiments-addlink-context-button-accept' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-yes' ]
	} );
	this.noButton = new OO.ui.ToggleButtonWidget( {
		icon: 'close',
		label: mw.msg( 'growthexperiments-addlink-context-button-reject' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-no' ]
	} );
	this.reopenRejectionDialogButton = new OO.ui.ButtonWidget( {
		icon: 'ellipsis',
		framed: false,
		title: mw.msg( 'growthexperiments-addlink-rejectiondialog-reopen-button-title' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-no-reopen' ]
	} );
	this.$acceptanceButtonGroup = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons-acceptance-group' );
	this.$acceptanceButtonGroup.append( [
		this.yesButton.$element,
		this.noButton.$element,
		this.reopenRejectionDialogButton.$element
	] );
	this.yesButton.connect( this, { click: [ 'onYesButtonClicked' ] } );
	this.noButton.connect( this, { click: [ 'onNoButtonClicked' ] } );
	this.reopenRejectionDialogButton.connect( this, { click: [ 'setAccepted', false ] } );
	this.$buttons.append( this.$acceptanceButtonGroup, this.$navButtonGroup );
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
		$indicator = $( '<span>' ).addClass( 'mw-ge-recommendedLinkContextItem-progress-indicator' );
		this.$progress.append( $indicator );
	}
};

// UI update methods based on recommendation-specific "computed properties"

/**
 * Render content specific to the current recommendation
 *
 * @throws Will throw an error if there's no DataModel
 */
RecommendedLinkToolbarDialog.prototype.updateContentForCurrentRecommendation = function () {
	var $linkPreviewBody;
	if ( !this.currentDataModel ) {
		throw new Error( 'No DataModel' );
	}
	// In transient context items, the context object is passed so that it can be resized.
	$linkPreviewBody = ve.ui.MWInternalLinkContextItem.static.generateBody(
		ve.init.platform.linkCache,
		this.currentDataModel,
		this.surface.getModel().getDocument().getHtmlDocument(),
		{
			updateDimensions: this.updateDimensions.bind( this )
		}
	);
	this.$linkPreview.html( $linkPreviewBody );
	this.updateButtonStates();
	this.updateProgressIndicators();
};

/**
 * Update button states based on the current DataModel
 */
RecommendedLinkToolbarDialog.prototype.updateButtonStates = function () {
	this.yesButton.setValue( this.currentDataModel.isAccepted() );
	this.noButton.setValue( this.currentDataModel.isRejected() );
	this.reopenRejectionDialogButton.toggle( this.currentDataModel.isRejected() );
	this.prevButton.setDisabled( this.currentIndex === 0 );
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
			indicator.classList.add( 'mw-ge-recommendedLinkContextItem-progress-indicator-selected' );
		} else {
			indicator.classList.remove( 'mw-ge-recommendedLinkContextItem-progress-indicator-selected' );
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
		$linkPreviewText = this.$linkPreview.find( '.ve-ui-linkContextItem-link' ),
		nextButtonLeft = $nextButton.offset().left,
		linkPreviewTextLeft = $linkPreviewText.offset().left,
		canOverflowStateAlign = false,
		availableWidth;

	// This doesn't have to be re-computed (doesn't change upon window resize).
	this.acceptanceButtonsWidth = acceptanceButtonsWidth;

	if ( this.surface.getDir() === 'rtl' ) {
		availableWidth = $linkPreviewText.width() - ( nextButtonLeft + $nextButton.width() );
	} else {
		availableWidth = nextButtonLeft - linkPreviewTextLeft;
	}

	if ( availableWidth < acceptanceButtonsWidth ) {
		canOverflowStateAlign = acceptanceButtonsWidth + linkPreviewTextLeft < this.$buttons.width();
		this.$acceptanceButtonGroup.addClass( 'overflow-state' );
		this.$acceptanceButtonGroup.toggleClass( 'overflow-state-left-aligned', canOverflowStateAlign );
	} else {
		this.$acceptanceButtonGroup.removeClass( 'overflow-state' );
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
	this.surface.dialogs.openWindow( 'message', {
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
			ve.init.target.tryTeardown( true, 'navigate-read' ).then( function () {
				var SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
					suggestedEditSession = SuggestedEditSession.getInstance();

				suggestedEditSession.setTaskState( SuggestedEditSession.static.STATES.CANCELLED );
				suggestedEditSession.showPostEditDialog( { resetSession: true } );
			} );
		}
	} );
};

// Helpers

/**
 * Check whether the last recommendation is shown
 *
 * @return {boolean}
 */
RecommendedLinkToolbarDialog.prototype.isLastRecommendationSelected = function () {
	return this.currentIndex === this.linkRecommendationFragments.length - 1;
};

/**
 * Set getIconForLink function for the current linkCache object
 *
 * @param {Function} iconFunction
 */
RecommendedLinkToolbarDialog.prototype.setLinkCacheIconFunction = function ( iconFunction ) {
	ve.init.platform.linkCache.constructor.static.getIconForLink = iconFunction;
};

module.exports = RecommendedLinkToolbarDialog;
