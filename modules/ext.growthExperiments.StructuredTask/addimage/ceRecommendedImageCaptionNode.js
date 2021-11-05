var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

/**
 * @class mw.libs.ge.ce.RecommendedImageCaptionNode
 * @extends ve.ce.MWImageCaptionNode
 * @constructor
 */
function CERecommendedImageCaptionNode() {
	CERecommendedImageCaptionNode.super.apply( this, arguments );
	/**
	 * @property {mw.libs.ge.AddImageArticleTarget} articleTarget
	 */
	this.articleTarget = ve.init.target;
	this.setupPlaceholder();
	this.setupHelpButton();
	this.$element.addClass( [
		'mw-ge-recommendedImageCaption',
		'mw-ge-recommendedImageCaption--with-placeholder'
	] ).append( this.helpButton.$element );
	this.model.on( 'update', this.onModelUpdate.bind( this ) );
	/**
	 * FIXME: Bring up virtual keyboard if there's no onboarding
	 * This probably won't work on iOS without some hacks since programmatic focus isn't allowed
	 * (see https://bugs.webkit.org/show_bug.cgi?id=195884#c4).
	 */
}

OO.inheritClass( CERecommendedImageCaptionNode, ve.ce.MWImageCaptionNode );

CERecommendedImageCaptionNode.static.name = 'mwGeRecommendedImageCaption';

/**
 * Check whether there's any text in the caption field.
 * This is called from mw.libs.ge.ce.AddImageLinearDeleteKeyDownHandler to determine whether the
 * default delete handler should be called and when CERecommendedImageCaptionNode is updated.
 *
 * @return {boolean}
 */
CERecommendedImageCaptionNode.prototype.isEmpty = function () {
	// The caption content is held in VeDmParagraphNode.
	var paragraphNode = this.model.getChildren()[ 0 ];
	if ( paragraphNode ) {
		return paragraphNode.getLength() === 0;
	}
	// The model's update event is fired upon node deletion.
	return true;
};

/**
 * Get localized placeholder text
 *
 * @return {string}
 */
CERecommendedImageCaptionNode.prototype.getPlaceholderText = function () {
	return mw.message( 'growthexperiments-addimage-caption-placeholder' ).params( [
		suggestedEditSession.getCurrentTitle().getNameText()
	] ).text();
};

/**
 * Set up placeholder text (make contenteditable element behave like an input field)
 */
CERecommendedImageCaptionNode.prototype.setupPlaceholder = function () {
	// Placeholder will be rendered via a pseudo-element so that the text appears in the
	// contenteditable element but doesn't get included as the actual content changed.
	this.$element.attr( 'placeholder', this.getPlaceholderText() );
	this.$element.on( 'click', function () {
		this.$element.removeClass( 'mw-ge-recommendedImageCaption--with-placeholder' );
		this.articleTarget.getSurface().getView().setActiveNode( this );
		this.articleTarget.logSuggestionInteraction( 'focus', 'caption_entry' );
	}.bind( this ) );
};

/**
 * Set up button for re-opening caption onboarding dialog
 */
CERecommendedImageCaptionNode.prototype.setupHelpButton = function () {
	var articleTarget = this.articleTarget;
	this.helpButton = new OO.ui.ButtonWidget( {
		icon: 'helpNotice',
		framed: false,
		classes: [ 'mw-ge-recommendedImageCaption-help-button' ],
		invisibleLabel: true,
		lable: mw.message( 'growthexperiments-addimage-inspector-help-button' ).text()
	} );
	this.helpButton.on( 'click', function () {
		articleTarget.logSuggestionInteraction( 'view_help', 'caption_entry' );
		articleTarget.showCaptionInfoDialog();
	} );
};

/**
 * Disable the save button if there's no caption text
 */
CERecommendedImageCaptionNode.prototype.onModelUpdate = function () {
	this.articleTarget.setDisabledSaveTool( this.isEmpty() );
};

module.exports = CERecommendedImageCaptionNode;
