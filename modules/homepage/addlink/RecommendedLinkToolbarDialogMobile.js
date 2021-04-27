var RecommendedLinkToolbarDialog = require( 'ext.growthExperiments.AddLink' ).RecommendedLinkToolbarDialog;

/**
 * @class mw.libs.ge.RecommendedLinkToolbarDialogMobile
 * @extends mw.libs.ge.RecommendedLinkToolbarDialog
 * @constructor
 */
function RecommendedLinkToolbarDialogMobile() {
	RecommendedLinkToolbarDialogMobile.super.apply( this, arguments );
	this.$element.addClass( [ 'mw-ge-recommendedLinkContextItem-mobile' ] );
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
	// Account for area covered by toolbar and link inspector
	var padding = {
		top: 60,
		bottom: Math.max( this.$element.height() + 100, 300 )
	};
	RecommendedLinkToolbarDialogMobile.super.prototype.selectAnnotationView.call( this );
	ve.scrollIntoView(
		this.annotationView.$element.get( 0 ), { padding: padding }
	);
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

module.exports = RecommendedLinkToolbarDialogMobile;
