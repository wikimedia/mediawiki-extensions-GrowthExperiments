var RecommendedLinkToolbarDialog = require( 'ext.growthExperiments.AddLink' ).RecommendedLinkToolbarDialog;

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogMobile
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialogMobile() {
	RecommendedLinkToolbarDialogMobile.super.apply( this, arguments );
	this.$element.addClass( [ 'mw-ge-recommendedLinkContextItem-mobile' ] );
	this.topOffset = 25;
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
	RecommendedLinkToolbarDialogMobile.super.prototype.afterSetupProcess.call( this );
	// Disable virtual keyboard when tapping on areas other than annotations
	// (inputmode will need to be updated when editing link text is supported)
	this.setSurfaceInputMode( 'none' );
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
	if ( isLastRecommendationSelected && this.currentDataModel.isAccepted() ) {
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
	// Without deactivation, virtual keyboard shows up upon selection.
	this.surface.getView().activate();
	this.surface.getView().deactivate( false, false, true );
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
 * Set inputmode attribute on the document
 *
 * @param {string} inputMode
 * @private
 */
RecommendedLinkToolbarDialogMobile.prototype.setSurfaceInputMode = function ( inputMode ) {
	var documentNode = this.surface.getView().$element.find( '.ve-ce-documentNode' ).get( 0 );
	if ( documentNode ) {
		documentNode.inputMode = inputMode;
	}
};

module.exports = RecommendedLinkToolbarDialogMobile;
