var DmRecommendedLinkAnnotation = require( './dmRecommendedLinkAnnotation.js' ),
	CeRecommendedLinkAnnotation = require( './ceRecommendedLinkAnnotation.js' );

/**
 * @class mw.libs.ge.RecommendedLinkContextItem
 * @extends ve.ui.MWInternalLinkContextItem
 * @constructor
 */
function RecommendedLinkContextItem() {
	RecommendedLinkContextItem.super.apply( this, arguments );

	this.$element.addClass( 'mw-ge-recommendedLinkContextItem' );
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
	var recommendationInfo = this.getRecommendationInfo(),
		// This generateBody() call is copied from the parent method
		$linkPreview = this.constructor.static.generateBody(
			ve.init.platform.linkCache,
			this.model,
			this.context.getSurface().getModel().getDocument().getHtmlDocument(),
			this.context
		),
		introLabel = new OO.ui.LabelWidget( {
			label: mw.msg( 'growthexperiments-addlink-context-intro' ),
			classes: [ 'mw-ge-recommendedLinkContextItem-introLabel' ]
		} ),
		$buttons = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-buttons' );

	this.yesButton = new OO.ui.ButtonWidget( {
		icon: this.context.isMobile() ? undefined : 'check',
		label: mw.msg( 'growthexperiments-addlink-context-button-accept' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-yes' ]
	} );
	this.noButton = new OO.ui.ButtonWidget( {
		icon: this.context.isMobile() ? undefined : 'cancel',
		label: mw.msg( 'growthexperiments-addlink-context-button-reject' ),
		classes: [ 'mw-ge-recommendedLinkContextItem-buttons-no' ]
	} );
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

	this.yesButton.setFlags( this.model.isAccepted() ? [ 'progressive', 'primary' ] : [] );
	this.noButton.setFlags( this.model.isRejected() ? [ 'destructive', 'primary' ] : [] );
	this.prevButton.setDisabled( recommendationInfo.index === 0 );
	this.nextButton.setDisabled( recommendationInfo.index === recommendationInfo.total - 1 );

	this.yesButton.connect( this, { click: [ 'setAccepted', true ] } );
	this.noButton.connect( this, { click: [ 'setAccepted', false ] } );
	this.prevButton.connect( this, { click: [ 'moveToSuggestion', recommendationInfo.index - 1 ] } );
	this.nextButton.connect( this, { click: [ 'moveToSuggestion', recommendationInfo.index + 1 ] } );

	// TODO actually do something when the edit button is clicked (T267696)
	this.editButton.$element.addClass( 'mw-ge-recommendedLinkContextItem-editButton' );

	$buttons.append(
		this.prevButton.$element,
		this.yesButton.$element,
		this.noButton.$element,
		this.nextButton.$element
	);

	this.$body.append(
		introLabel.$element,
		// Move the edit button into the body, but only on desktop; on mobile, leave it where it is
		this.context.isMobile() ? $( [] ) : this.editButton.$element,
		$linkPreview,
		$buttons
	);

	// The label isn't visible on desktop, but it is on mobile (because of the parent's
	// implementation of generateBody()). We have to call updateLabelPreview() (which the parent
	// method does too), otherwise the label stays empty.
	if ( this.context.isMobile() ) {
		this.updateLabelPreview();
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
		if ( i === index ) {
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
 * @return {{ index: number, total: number, recommendationId: string, fragment: ve.dm.SurfaceFragment }|null}
 */
RecommendedLinkContextItem.prototype.getRecommendationInfo = function () {
	var i, thisId, recommendationFragments;
	if ( this.recommendationInfo !== undefined ) {
		return this.recommendationInfo;
	}

	this.recommendationInfo = null;
	thisId = this.model.getAttribute( 'recommendationId' );
	recommendationFragments = this.context.getSurface().linkRecommendationFragments;
	for ( i = 0; i < recommendationFragments.length; i++ ) {
		if ( recommendationFragments[ i ].recommendationId === thisId ) {
			this.recommendationInfo = $.extend( {
				index: i,
				total: recommendationFragments.length
			}, recommendationFragments[ i ] );
		}
	}
	return this.recommendationInfo;
};

/**
 * Mark this suggestion as accepted or rejected.
 *
 * Commits a transaction that removes the existing annotation and adds a new one that is
 * identical except for the 'recommendationAccepted' attribute. This will cause the context item
 * to be destroyed, and a new one to be created for the new annotation.
 *
 * @private
 * @param {boolean} accepted True if accepted, false if rejected
 */
RecommendedLinkContextItem.prototype.setAccepted = function ( accepted ) {
	var feedbackDialogPromise,
		recommendationInfo = this.getRecommendationInfo(),
		surfaceModel = this.context.getSurface().getModel(),
		oldReadOnly = surfaceModel.isReadOnly();

	// Temporarily disable read-only mode
	surfaceModel.setReadOnly( false );

	this.applyToAnnotations( function ( fragment, annotation ) {
		fragment.setAutoSelect( false );
		fragment.annotateContent( 'clear', annotation );
		fragment.annotateContent( 'set', new DmRecommendedLinkAnnotation( $.extend( true,
			annotation.getElement(),
			{ attributes: { recommendationAccepted: accepted } }
		) ) );
	} );

	// Re-enable read-only mode (if it was previously enabled)
	surfaceModel.setReadOnly( oldReadOnly );

	if ( accepted ) {
		feedbackDialogPromise = $.Deferred().resolve();
	} else {
		feedbackDialogPromise = this.context.getSurface().dialogs
			.openWindow( 'recommendedLinkRejection' ).closed
			.then( function ( /* closedData */ ) {
				// TODO store closedData.reason somewhere
			} );
	}

	if ( recommendationInfo.index < recommendationInfo.total - 1 ) {
		// Move to the next suggestion
		feedbackDialogPromise.then( function () {
			this.moveToSuggestion( this.getRecommendationInfo().index + 1 );
		}.bind( this ) );
	}
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
	// Force the context item to appear
	this.context.getSurface().getView().selectAnnotation( function ( annotationView ) {
		return annotationView instanceof CeRecommendedLinkAnnotation;
	} );
	if ( OO.ui.isMobile() ) {
		// On mobile, deactivate the surface so that the context appears
		this.context.getSurface().getView().deactivate( false, false, true );
	}
};

module.exports = RecommendedLinkContextItem;
