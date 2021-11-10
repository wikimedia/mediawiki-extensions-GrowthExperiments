var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
	MIN_CAPTION_LENGTH = 5;

/**
 * @typedef mw.libs.ge.ce.RecommendedImageCaptionWarning
 * @property {id} id ID of the validation rule
 * @property {string} text Localized warning text
 */

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
	/**
	 * Localized warning text if the caption doesn't meet the validation rules
	 *
	 * @type {mw.libs.ge.ce.RecommendedImageCaptionWarning[]}
	 */
	this.warnings = [];
	this.setupPlaceholder();
	this.setupHelpButton();
	this.$element.addClass( [
		'mw-ge-recommendedImageCaption',
		'mw-ge-recommendedImageCaption--with-placeholder'
	] ).append( this.helpButton.$element );
	this.$element.on( 'blur', this.showWarningIfNeeded.bind( this ) );
	this.model.on( 'update', this.onCaptionChanged.bind( this ) );
	/**
	 * FIXME: Bring up virtual keyboard if there's no onboarding
	 * This probably won't work on iOS without some hacks since programmatic focus isn't allowed
	 * (see https://bugs.webkit.org/show_bug.cgi?id=195884#c4).
	 */
}

OO.inheritClass( CERecommendedImageCaptionNode, ve.ce.MWImageCaptionNode );

CERecommendedImageCaptionNode.static.name = 'mwGeRecommendedImageCaption';

/**
 * Get the length of the caption text
 *
 * @return {number}
 */
CERecommendedImageCaptionNode.prototype.getCaptionLength = function () {
	// The caption content is held in VeDmParagraphNode.
	var paragraphNode = this.model.getChildren()[ 0 ];
	return paragraphNode ? paragraphNode.getLength() : 0;
};

/**
 * Check whether there's any text in the caption field.
 * This is called from mw.libs.ge.ce.AddImageLinearDeleteKeyDownHandler to determine whether the
 * default delete handler should be called and when CERecommendedImageCaptionNode is updated.
 *
 * @return {boolean}
 */
CERecommendedImageCaptionNode.prototype.isEmpty = function () {
	return this.getCaptionLength() === 0;
};

/**
 * Check whether the caption meets the validation rules
 *
 * @return {boolean}
 */
CERecommendedImageCaptionNode.prototype.isValid = function () {
	return this.warnings.length === 0;
};

/**
 * Get localized placeholder text
 *
 * @return {string}
 */
CERecommendedImageCaptionNode.prototype.getPlaceholderHtml = function () {
	return mw.message( 'growthexperiments-addimage-caption-placeholder' ).params( [
		suggestedEditSession.getCurrentTitle().getNameText()
	] ).parse();
};

/**
 * Set up placeholder text (make contenteditable element behave like an input field)
 */
CERecommendedImageCaptionNode.prototype.setupPlaceholder = function () {
	var $placeholder = $( '<p>' )
		.addClass( 'mw-ge-recommendedImageCaption-placeholder' )
		.html( this.getPlaceholderHtml() );
	this.$element.on( 'click', function () {
		// Prevent the field height from changing when the placeholder node is removed
		this.$element.css( 'min-height', this.$element.height() );
		$placeholder.detach();
		// This programmatic focus works on iOS because it's inside a click event listener.
		this.$element.focus();
		this.$element.removeClass( 'mw-ge-recommendedImageCaption--with-placeholder' );
		this.articleTarget.logSuggestionInteraction( 'focus', 'caption_entry' );
	}.bind( this ) );
	this.$element.prepend( $placeholder );
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
		label: mw.message( 'growthexperiments-addimage-inspector-help-button' ).text()
	} );
	this.helpButton.on( 'click', function () {
		articleTarget.logSuggestionInteraction( 'view_help', 'caption_entry' );
		articleTarget.showCaptionInfoDialog();
	} );
};

/**
 * Get the warning label to be used with this.warningWidget
 *
 * @return {jQuery}
 */
CERecommendedImageCaptionNode.prototype.getWarningLabel = function () {
	var $label = $( '<div>' );
	this.warnings.forEach( function ( warningData ) {
		$label.append( $( '<div>' ).text( warningData.text ) );
	} );
	return $label;
};

/**
 * Show the warning if the caption is invalid
 */
CERecommendedImageCaptionNode.prototype.showWarningIfNeeded = function () {
	if ( this.isValid() ) {
		return;
	}
	var $warningLabel = this.getWarningLabel();
	this.toggleInputWarningState( true );

	if ( this.warningWidget ) {
		this.warningWidget.setLabel( $warningLabel );
		this.warningWidget.toggle( true );
	} else {
		this.warningWidget = new OO.ui.MessageWidget( {
			type: 'error',
			inline: true,
			label: $warningLabel,
			classes: [ 'mw-ge-recommendedImageCaption-warning' ]
		} );
		this.$element.after( this.warningWidget.$element );
	}
	this.articleTarget.logSuggestionInteraction(
		'validate',
		'caption_entry',
		{
			// eslint-disable-next-line camelcase
			validation_rules: this.warnings.map( function ( warningData ) {
				return warningData.id;
			} )
		}
	);
};

/**
 * Hide the warning if it's shown
 */
CERecommendedImageCaptionNode.prototype.hideWarningIfNeeded = function () {
	if ( !this.isValid() ) {
		return;
	}
	if ( this.warningWidget ) {
		this.warningWidget.toggle( false );
	}
	this.toggleInputWarningState( false );
};

/**
 * Validate the current caption value and set this.isCaptionValid accordingly
 */
CERecommendedImageCaptionNode.prototype.validateCaption = function () {
	this.warnings = [];
	if ( this.getCaptionLength() < MIN_CAPTION_LENGTH ) {
		this.warnings.push( this.getLengthWarning() );
	}
	// Update the warning if it's already shown
	if ( this.warningWidget && this.warningWidget.isVisible() ) {
		this.showWarningIfNeeded();
	}
};

/**
 * Disable the save button if there's no caption text,
 * validate the updated caption text and hide the warning accordingly
 */
CERecommendedImageCaptionNode.prototype.onCaptionChanged = function () {
	this.validateCaption();
	this.articleTarget.setDisabledSaveTool( !this.isValid() );
	this.hideWarningIfNeeded();
};

/**
 * Get the length warning data
 *
 * @return {mw.libs.ge.ce.RecommendedImageCaptionWarning}
 */
CERecommendedImageCaptionNode.prototype.getLengthWarning = function () {
	return {
		id: 'too short',
		text: mw.message( 'growthexperiments-addimage-caption-warning-tooshort' ).params(
			[ mw.language.convertNumber( MIN_CAPTION_LENGTH ) ]
		).text()
	};
};

/**
 * Update the state of the caption box based on whether the validation warning in shown
 *
 * @param {boolean} shouldShowWarning Whether the warning state should be applied
 */
CERecommendedImageCaptionNode.prototype.toggleInputWarningState = function ( shouldShowWarning ) {
	this.$element.toggleClass( 'mw-ge-recommendedImageCaption--with-warning', shouldShowWarning );
};

module.exports = CERecommendedImageCaptionNode;
