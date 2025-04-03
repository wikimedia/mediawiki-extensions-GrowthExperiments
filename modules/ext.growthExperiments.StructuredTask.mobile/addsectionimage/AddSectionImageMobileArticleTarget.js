const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskMobileArticleTarget = require( '../StructuredTaskMobileArticleTarget.js' ),
	AddSectionImageArticleTarget = StructuredTask.addSectionImage().AddSectionImageArticleTarget,
	ImageSuggestionInteractionLogger = StructuredTask.addSectionImage().ImageSuggestionInteractionLogger;

/**
 * Mobile version of AddSectionImageArticleTarget
 *
 * @class mw.libs.ge.AddSectionImageMobileArticleTarget
 * @extends mw.libs.ge.StructuredTaskMobileArticleTarget
 * @mixes mw.libs.ge.AddSectionImageArticleTarget
 * @constructor
 */
function AddSectionImageMobileArticleTarget() {
	AddSectionImageMobileArticleTarget.super.apply( this, arguments );
	AddSectionImageArticleTarget.call( this );
	// FIXME modify?
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new ImageSuggestionInteractionLogger( { is_mobile: true } );
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddSectionImageMobileArticleTarget, StructuredTaskMobileArticleTarget );
OO.mixinClass( AddSectionImageMobileArticleTarget, AddSectionImageArticleTarget );

/** @override **/
AddSectionImageMobileArticleTarget.prototype.prepareSaveWithoutShowingDialog = function () {
	const promise = ve.createDeferred(),
		$overlay = this.getSurface().getGlobalOverlay().$element;
	// FIXME modify?
	$overlay.addClass( 'mw-ge-addImageMobileArticleTarget--overlay-shown' );
	promise.then( () => {
		this.restorePlaceholderTitle();
		// FIXME modify?
		$overlay.removeClass( 'mw-ge-addImageMobileArticleTarget--overlay-shown' );
	} );
	this.updatePlaceholderTitle(
		// FIXME modify?
		mw.message( 'growthexperiments-addimage-submitting-title' ).text(),
		true
	);
	this.toggleEditModeTool( false );
	return promise;
};

module.exports = AddSectionImageMobileArticleTarget;
