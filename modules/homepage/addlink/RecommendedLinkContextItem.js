var DmRecommendedLinkAnnotation = require( './dmRecommendedLinkAnnotation.js' ),
	CeRecommendedLinkAnnotation = require( './ceRecommendedLinkAnnotation.js' );

/**
 * @class mw.libs.ge.RecommendedLinkContextItem
 * @extends ve.ui.MWInternalLinkContextItem
 * @constructor
 */
function RecommendedLinkContextItem() {
	RecommendedLinkContextItem.super.apply( this, arguments );

	this.$element.addClass( [
		'mw-ge-recommendedLinkContextItem',
		this.context.isMobile() ? 'mw-ge-recommendedLinkContextItem-mobile' : 'mw-ge-recommendedLinkContextItem-desktop'
	] );
}

OO.inheritClass( RecommendedLinkContextItem, ve.ui.MWInternalLinkContextItem );

RecommendedLinkContextItem.static.name = 'mwGeRecommendedLink';
RecommendedLinkContextItem.static.modelClasses = [ DmRecommendedLinkAnnotation ];
RecommendedLinkContextItem.static.clearable = false;
RecommendedLinkContextItem.static.icon = 'robot';
// TODO make a command? Reuse an existing one? Likely needed to make the edit button work (T267696)
RecommendedLinkContextItem.static.commandName = 'TODO';

RecommendedLinkContextItem.prototype.setup = function () {
	// Don't call the parent method, because we want to build a slightly different UI
	var recommendationInfo = this.getRecommendationInfo(), introLabel;

	/**
	 * When the annotation is clicked on (rather than using buttons in the context item),
	 * the data model from the surface can be that of the prior annotation.
	 * When this is the case, manually select the fragment to make sure the right
	 * data model is updated.
	 */
	if ( this.hasWrongDataModel() ) {
		this.moveToSuggestion( recommendationInfo.index );
		return;
	}

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

	// This generateBody() call is copied from the parent method
	this.$linkPreview = this.constructor.static.generateBody(
		ve.init.platform.linkCache,
		this.model,
		this.context.getSurface().getModel().getDocument().getHtmlDocument(),
		this.context
	);
	this.$linkPreview.addClass( 'mw-ge-recommendedLinkContextItem-linkPreview' );

	introLabel = new OO.ui.LabelWidget( {
		label: mw.msg( 'growthexperiments-addlink-context-intro' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-introLabel' ]
	} );
	this.$buttons = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons' );

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
	this.reopenRejectionDialogButton.toggle( this.model.isRejected() );
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

	this.setLabel( mw.msg(
		'growthexperiments-addlink-context-title',
		mw.language.convertNumber( recommendationInfo.index + 1 ),
		mw.language.convertNumber( recommendationInfo.total )
	) );
	this.$title.append( this.buildProgressIndicators( recommendationInfo.index, recommendationInfo.total ) );

	this.yesButton.setValue( this.model.isAccepted() );
	this.noButton.setValue( this.model.isRejected() );
	this.prevButton.setDisabled( recommendationInfo.index === 0 );

	this.yesButton.connect( this, { click: [ 'setAccepted', this.model.isAccepted() ? null : true ] } );
	this.noButton.connect( this, { click: [ 'setAccepted', this.model.isRejected() ? null : false ] } );
	this.reopenRejectionDialogButton.connect( this, { click: [ 'setAccepted', false ] } );
	this.prevButton.connect( this, { click: [ 'moveToSuggestion', recommendationInfo.index - 1 ] } );
	this.nextButton.connect( this, { click: [ 'onNextActionClicked', recommendationInfo.index + 1 ] } );

	// link editing is disabled for now (T267696)
	this.editButton.toggle( false );
	this.$acceptanceButtonGroup = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons-acceptance-group' );
	this.$acceptanceButtonGroup.append( [
		this.yesButton.$element,
		this.noButton.$element,
		this.reopenRejectionDialogButton.$element
	] );

	this.$navButtonGroup = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons-nav-group' );
	this.$navButtonGroup.append( [ this.prevButton.$element, this.nextButton.$element ] );

	this.$buttons.append( [ this.$acceptanceButtonGroup, this.$navButtonGroup ] );
	this.$body.append(
		introLabel.$element,
		this.$linkPreview
	);

	// On mobile, add buttons to $foot to make sure they are always visible.
	this.$buttons.appendTo( this.context.isMobile() ? this.$foot : this.$body );

	// "Link" label is appended to old body (this.$body refers to innerBody)
	// See ve.ui.LinkContextItem's constructor
	this.$element.find( '.ve-ui-linkContextItem-link-label' ).addClass( 'oo-ui-element-hidden' );
	this.$actions.remove();

	// The label isn't visible on desktop, but it is on mobile (because of the parent's
	// implementation of generateBody()). We have to call updateLabelPreview() (which the parent
	// method does too), otherwise the label stays empty.
	if ( this.context.isMobile() ) {
		this.updateLabelPreview();
		this.setupHelpButton();
		this.updateActionButtonsMode();
		$( window ).on( 'resize',
			OO.ui.debounce( this.updateActionButtonsMode.bind( this ), 250 )
		);
	} else {
		// On desktop, wait until the buttons show up
		setTimeout( this.updateActionButtonsMode.bind( this ) );
	}
};

/**
 * Build a series of progress indicator spans, one of which is marked as selected.
 *
 * @private
 * @param {number} index Zero-based index of the selected indicator
 * @param {number} total Total number of indicators to build
 * @return {jQuery} <span> tag containing progress-indicator <span>s
 */
RecommendedLinkContextItem.prototype.buildProgressIndicators = function ( index, total ) {
	// TODO enforce the maximum of 10 recommendations, here and elsewhere (T267703)
	var i, $indicator,
		$progress = $( '<span>' ).addClass( 'mw-ge-recommendedLinkContextItem-progress' );
	for ( i = 0; i < total; i++ ) {
		$indicator = $( '<span>' ).addClass( 'mw-ge-recommendedLinkContextItem-progress-indicator' );
		if ( i <= index ) {
			$indicator.addClass( 'mw-ge-recommendedLinkContextItem-progress-indicator-selected' );
		}
		$progress.append( $indicator );
	}
	return $progress;
};

/**
 * Get information about this recommendation's place in the document.
 *
 * @private
 * @return {?{ index: number, total: number, recommendationWikitextOffset: number, fragment: ve.dm.SurfaceFragment }}
 */
RecommendedLinkContextItem.prototype.getRecommendationInfo = function () {
	var i, thisOffset, recommendationFragments;
	if ( this.recommendationInfo !== undefined ) {
		return this.recommendationInfo;
	}

	this.recommendationInfo = null;
	thisOffset = this.model.getAttribute( 'recommendationWikitextOffset' );
	recommendationFragments = this.context.getSurface().linkRecommendationFragments;
	for ( i = 0; i < recommendationFragments.length; i++ ) {
		if ( recommendationFragments[ i ].recommendationWikitextOffset === thisOffset ) {
			this.recommendationInfo = $.extend( {
				index: i,
				total: recommendationFragments.length
			}, recommendationFragments[ i ] );
		}
	}
	return this.recommendationInfo;
};

/**
 * Mark this suggestion as accepted, rejected or undecided, and store rejection reason if given.
 *
 * Commits a transaction that removes the existing annotation and adds a new one that is
 * identical except for the 'recommendationAccepted' attribute. This will cause the context item
 * to be destroyed, and a new one to be created for the new annotation.
 *
 * @private
 * @param {boolean|null} accepted True if accepted, false if rejected, null if undecided
 * (the yes/no button has been toggled).
 */
RecommendedLinkContextItem.prototype.setAccepted = function ( accepted ) {
	var acceptancePromise,
		recommendationInfo = this.getRecommendationInfo(),
		surfaceModel = this.context.getSurface().getModel(),
		oldReadOnly = surfaceModel.isReadOnly(),
		attributes = {
			recommendationAccepted: accepted,
			rejectionReason: undefined
		};

	// Temporarily disable read-only mode
	surfaceModel.setReadOnly( false );

	if ( accepted || accepted === null ) {
		acceptancePromise = $.Deferred().resolve();
	}
	if ( accepted === false ) {
		acceptancePromise = this.context.getSurface().dialogs
			.openWindow( 'recommendedLinkRejection', this.model.getRejectionReason() ).closed
			.then( function ( closedData ) {
				return closedData && closedData.reason || this.model.getRejectionReason();
			}.bind( this ) );
	}

	acceptancePromise.then( function ( rejectionReason ) {
		if ( rejectionReason ) {
			attributes.rejectionReason = rejectionReason;
		}
		this.applyToAnnotations( function ( fragment, annotation ) {
			fragment.setAutoSelect( false );
			fragment.annotateContent( 'clear', annotation );
			fragment.annotateContent( 'set', new DmRecommendedLinkAnnotation( $.extend( true,
				annotation.getElement(),
				{ attributes: attributes }
			) ) );
		} );
	}.bind( this ) ).then( function () {
		// Re-enable read-only mode (if it was previously enabled)
		surfaceModel.setReadOnly( oldReadOnly );
	} ).then( this.onAcceptanceChanged.bind( this ) );

	// Auto-advance
	if ( this.context.isMobile() ) {
		if ( recommendationInfo.index < recommendationInfo.total - 1 ) {
			// Move to the next suggestion
			acceptancePromise.then( function () {
				this.moveToSuggestion( recommendationInfo.index + 1 );
			}.bind( this ) );
		} else if ( accepted && recommendationInfo.index === recommendationInfo.total - 1 ) {
			// Publish changes when the user accepted the last suggestion
			acceptancePromise.then( function () {
				mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
			} );
		}
	}

	// Show the new context item created when acceptance changed
	acceptancePromise.then( function () {
		this.forceContextItemToAppear();
		this.onAcceptanceChanged();
	}.bind( this ) );
};

/**
 * Move the selection to a different suggestion.
 *
 * This will cause this context item to be destroyed, and a new one to be created for the
 * suggestion that we're navigating to.
 *
 * @param {number} index Zero-based index of the suggestion in the linkRecommendationFragments array
 */
RecommendedLinkContextItem.prototype.moveToSuggestion = function ( index ) {
	var fragment = this.context.getSurface().linkRecommendationFragments[ index ].fragment;
	fragment.select();
	this.forceContextItemToAppear();
};

/**
 * Force the context item to appear.
 */
RecommendedLinkContextItem.prototype.forceContextItemToAppear = function () {
	this.context.getSurface().getView().selectAnnotation( function ( annotationView ) {
		return annotationView instanceof CeRecommendedLinkAnnotation;
	} );
	if ( this.context.isMobile() ) {
		// On mobile, deactivate the surface so that the context appears (see ve.ce.Surface)
		// Deactivation logic is only executed if the surface isn't already de-activated
		this.context.getSurface().getView().activate();
		this.context.getSurface().getView().deactivate( false, false, true );
		// Update active annotations based on current model (instead of DOM cursor)
		this.context.getSurface().getView().updateActiveAnnotations( true );
	}
};

/**
 * Replace close button with help button
 *
 * @private
 */
RecommendedLinkContextItem.prototype.setupHelpButton = function () {
	this.helpButton = new OO.ui.ButtonWidget( {
		classes: [ 'mw-ge-recommendedLinkContextItem-help-button' ],
		framed: false,
		icon: 'helpNotice',
		label: mw.message( 'growthexperiments-addlink-context-button-help' ).text(),
		invisibleLabel: true
	} );
	this.helpButton.on( 'click', function () {
		mw.hook( 'growthExperiments.contextItem.openHelpPanel' ).fire();
	} );

	this.$head.append( this.helpButton.$element );

	if ( this.closeButton ) {
		this.closeButton.$element.remove();
	}
};

/**
 * Fire an event when a recommendation is accepted or rejected
 * This allows the publish button to be updated based on whether there are any acceptances.
 */
RecommendedLinkContextItem.prototype.onAcceptanceChanged = function () {
	var linkRecommendationFragments = this.context.getSurface().linkRecommendationFragments,
		hasAcceptedRecommendations = linkRecommendationFragments.some( function ( recommendation ) {
			var annotationSet = recommendation.fragment
				.getAnnotations().getAnnotationsByName( 'mwGeRecommendedLink' );
			return annotationSet.getLength() ? annotationSet.get( 0 ).isAccepted() : false;
		} );

	mw.hook( 'growthExperiments.machineSuggestionAcceptanceChanged' ).fire( hasAcceptedRecommendations );
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
RecommendedLinkContextItem.prototype.updateActionButtonsMode = function () {
	var acceptanceButtonsWidth = this.acceptanceButtonsWidth || this.$acceptanceButtonGroup.width(),
		$nextButton = this.nextButton.$element,
		$linkPreviewText = this.$linkPreview.find( '.ve-ui-linkContextItem-link' ),
		nextButtonLeft = $nextButton.offset().left,
		linkPreviewTextLeft = $linkPreviewText.offset().left,
		canOverflowStateAlign = false,
		availableWidth;

	// This doesn't have to be re-computed (doesn't change upon window resize).
	this.acceptanceButtonsWidth = acceptanceButtonsWidth;

	if ( document.dir === 'rtl' ) {
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

	this.context.updateDimensions();
};

/**
 * Check whether all recommendations were skipped
 *
 * @return {boolean}
 */
RecommendedLinkContextItem.prototype.allRecommendationsSkipped = function () {
	var linkRecommendationFragments = this.context.getSurface().linkRecommendationFragments;
	return linkRecommendationFragments.every( function ( recommendation ) {
		var annotationSet = recommendation.fragment
			.getAnnotations().getAnnotationsByName( 'mwGeRecommendedLink' );
		return annotationSet.getLength() ? annotationSet.get( 0 ).isUndecided() : false;
	} );
};

/**
 * Move the selection to the next suggestion if it exists, if the user is on the last recommendation:
 * fire an event to save the article if user decided on any of the recommendations
 * show skipped all suggestions dialog if user didn't decide on any of the recommendations
 *
 * @param {number} nextIndex Zero-based index of the next suggestion in the linkRecommendationFragments array
 */
RecommendedLinkContextItem.prototype.onNextActionClicked = function ( nextIndex ) {
	if ( nextIndex === this.getRecommendationInfo().total ) {
		if ( this.allRecommendationsSkipped() ) {
			// TODO: Show skip all suggestions dialog (T269658)
		} else {
			mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
		}
	} else {
		this.moveToSuggestion( nextIndex );
	}
};

/**
 * @inheritdoc
 */
RecommendedLinkContextItem.prototype.teardown = function () {
	RecommendedLinkContextItem.super.prototype.teardown.apply( this, arguments );
	$( window ).off( 'resize' );
	this.setLinkCacheIconFunction( this.originalGetIconForLink );
};

/**
 * Check if the data model of the context item is different from the current fragment's
 *
 * @return {boolean}
 */
RecommendedLinkContextItem.prototype.hasWrongDataModel = function () {
	var fragmentAnnotations = this.context.getSurface().getModel().getFragment().getAnnotations(),
		annotation = fragmentAnnotations.getAnnotationsByName( 'mwGeRecommendedLink' ).get( 0 );
	if ( !annotation ) {
		return false;
	}
	return annotation.getAttribute( 'recommendationWikitextOffset' ) !==
		this.model.getAttribute( 'recommendationWikitextOffset' );
};

/**
 * Set getIconForLink function for the current linkCache object
 *
 * @param {Function} iconFunction
 */
RecommendedLinkContextItem.prototype.setLinkCacheIconFunction = function ( iconFunction ) {
	ve.init.platform.linkCache.constructor.static.getIconForLink = iconFunction;
};

module.exports = RecommendedLinkContextItem;
