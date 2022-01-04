var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskMobileArticleTarget = require( '../StructuredTaskMobileArticleTarget.js' ),
	AddImageArticleTarget = StructuredTask.addImage().AddImageArticleTarget,
	ImageSuggestionInteractionLogger = StructuredTask.addImage().ImageSuggestionInteractionLogger;

/**
 * Mobile version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkMobileArticleTarget
 * @extends mw.libs.ge.StructuredTaskMobileArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddImageMobileArticleTarget() {
	AddImageMobileArticleTarget.super.apply( this, arguments );
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new ImageSuggestionInteractionLogger( { is_mobile: true } );
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddImageMobileArticleTarget, StructuredTaskMobileArticleTarget );
OO.mixinClass( AddImageMobileArticleTarget, AddImageArticleTarget );

/** @override **/
AddImageMobileArticleTarget.prototype.prepareSaveWithoutShowingDialog = function () {
	var promise = ve.createDeferred(),
		$overlay = this.getSurface().getGlobalOverlay().$element;
	$overlay.addClass( 'mw-ge-addImageMobileArticleTarget--overlay-shown' );
	promise.done( function () {
		this.restorePlaceholderTitle();
		$overlay.removeClass( 'mw-ge-addImageMobileArticleTarget--overlay-shown' );
	}.bind( this ) );
	this.updatePlaceholderTitle(
		mw.message( 'growthexperiments-addimage-submitting-title' ).text(),
		true
	);
	this.toggleEditModeTool( false );
	return promise;
};

module.exports = AddImageMobileArticleTarget;
