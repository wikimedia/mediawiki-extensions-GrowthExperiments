const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskMobileArticleTarget = require( '../StructuredTaskMobileArticleTarget.js' ),
	AddImageArticleTarget = StructuredTask.addImage().AddImageArticleTarget,
	ImageSuggestionInteractionLogger = StructuredTask.addImage().ImageSuggestionInteractionLogger;

/**
 * Mobile version of AddImageArticleTarget
 *
 * @class mw.libs.ge.AddImageMobileArticleTarget
 * @extends mw.libs.ge.StructuredTaskMobileArticleTarget
 * @mixes mw.libs.ge.AddImageArticleTarget
 * @constructor
 */
function AddImageMobileArticleTarget() {
	AddImageMobileArticleTarget.super.apply( this, arguments );
	AddImageArticleTarget.call( this );
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new ImageSuggestionInteractionLogger( { is_mobile: true } );
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddImageMobileArticleTarget, StructuredTaskMobileArticleTarget );
OO.mixinClass( AddImageMobileArticleTarget, AddImageArticleTarget );

/** @override **/
AddImageMobileArticleTarget.prototype.prepareSaveWithoutShowingDialog = function () {
	const promise = ve.createDeferred(),
		$overlay = this.getSurface().getGlobalOverlay().$element;
	$overlay.addClass( 'mw-ge-addImageMobileArticleTarget--overlay-shown' );
	promise.then( () => {
		this.restorePlaceholderTitle();
		$overlay.removeClass( 'mw-ge-addImageMobileArticleTarget--overlay-shown' );
	} );
	this.updatePlaceholderTitle(
		mw.message( 'growthexperiments-addimage-submitting-title' ).text(),
		true
	);
	this.toggleEditModeTool( false );
	return promise;
};

module.exports = AddImageMobileArticleTarget;
