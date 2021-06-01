var AddLink = require( 'ext.growthExperiments.AddLink' ),
	RecommendedLinkToolbarDialog = AddLink.RecommendedLinkToolbarDialog,
	LinkSuggestionInteractionLogger = AddLink.LinkSuggestionInteractionLogger;

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogMobile
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialogMobile() {
	RecommendedLinkToolbarDialogMobile.super.apply( this, arguments );
	this.$element.addClass( [ 'mw-ge-recommendedLinkContextItem-mobile' ] );
	this.topOffset = 25;
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: false,
		active_interface: 'recommendedlinktoolbar_dialog'
		/* eslint-enable camelcase */
	} );
}

OO.inheritClass( RecommendedLinkToolbarDialogMobile, RecommendedLinkToolbarDialog );

RecommendedLinkToolbarDialogMobile.static.size = 'full';
RecommendedLinkToolbarDialogMobile.static.position = 'below';

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.initialize = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.initialize.call( this );
	this.$labelPreview = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-labelPreview' );
	this.setupLabelPreview();
	this.$body.prepend( this.$labelPreview );
	this.setupHelpButton();
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.afterSetupProcess = function () {
	var ceSurface = this.surface.getView();
	// HACK: Disable virtual keyboard, text edit menu on the surface
	ceSurface.$documentNode.attr( 'contenteditable', false );
	ceSurface.$documentNode.addClass( 'mw-ge-user-select-none' );
	mw.hook( 'growthExperiments.addLinkOnboardingCompleted' ).add( function () {
		// If onboarding is completed after selecting first recommendation, the selection needs to
		// be scrolled into view since it wasn't in the viewport when onboarding was open.
		this.surface.scrollSelectionIntoView();
	}.bind( this ) );
	RecommendedLinkToolbarDialogMobile.super.prototype.afterSetupProcess.call( this );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.updateContentForCurrentRecommendation = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.updateContentForCurrentRecommendation.call( this );
	if ( this.annotationView ) {
		this.$labelPreviewText.text( this.annotationView.$element.text() );
	}
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.onAcceptanceChanged = function () {
	var isLastRecommendationSelected = this.isLastRecommendationSelected();
	RecommendedLinkToolbarDialogMobile.super.prototype.onAcceptanceChanged.call( this );
	// Auto-advance
	if ( isLastRecommendationSelected ) {
		mw.hook( 'growthExperiments.contextItem.saveArticle' ).fire();
	} else if ( !isLastRecommendationSelected ) {
		this.showRecommendationAtIndex( this.currentIndex + 1 );
	}
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.selectAnnotationView = function () {
	RecommendedLinkToolbarDialogMobile.super.prototype.selectAnnotationView.call( this );
	this.surface.scrollSelectionIntoView();
};

/**
 * Set up the template in which to show the text in the article the recommendation is for
 *
 * @private
 */
RecommendedLinkToolbarDialogMobile.prototype.setupLabelPreview = function () {
	this.$labelPreviewText = $( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-labelPreview-text' );
	this.$labelPreview.append( [
		$( '<div>' ).addClass( 'mw-ge-recommendedLinkContextItem-labelPreview-label' ).text(
			mw.message( 'growthexperiments-addlink-context-text-label' ).text()
		),
		this.$labelPreviewText
	] );
};

/**
 * Set up button that opens help panel
 *
 * @private
 */
RecommendedLinkToolbarDialogMobile.prototype.setupHelpButton = function () {
	var helpButton = new OO.ui.ButtonWidget( {
		classes: [ 'mw-ge-recommendedLinkContextItem-help-button' ],
		framed: false,
		icon: 'helpNotice',
		label: mw.message( 'growthexperiments-addlink-context-button-help' ).text(),
		invisibleLabel: true
	} );
	helpButton.on( 'click', function () {
		mw.hook( 'growthExperiments.contextItem.openHelpPanel' ).fire();
	} );
	this.$head.append( helpButton.$element );
};

/**
 * @inheritdoc
 */
RecommendedLinkToolbarDialogMobile.prototype.teardown = function () {
	var ceSurface = this.surface.getView();
	ceSurface.$documentNode.attr( 'contenteditable', true );
	ceSurface.$documentNode.removeClass( 'mw-ge-user-select-none' );
	return RecommendedLinkToolbarDialogMobile.super.prototype.teardown.apply( this, arguments );
};

module.exports = RecommendedLinkToolbarDialogMobile;
