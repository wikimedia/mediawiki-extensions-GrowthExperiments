const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskDesktopArticleTarget = require( '../StructuredTaskDesktopArticleTarget.js' ),
	SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
	suggestedEditSession = SuggestedEditSession.getInstance(),
	AddSectionImageArticleTarget = StructuredTask.addSectionImage().AddSectionImageArticleTarget,
	ImageSuggestionInteractionLogger = StructuredTask.addSectionImage().ImageSuggestionInteractionLogger;

/**
 * Desktop version of AddSectionImageArticleTarget
 *
 * @class mw.libs.ge.AddSectionImageDesktopArticleTarget
 * @extends mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @mixes mw.libs.ge.AddSectionImageArticleTarget
 * @constructor
 */
function AddSectionImageDesktopArticleTarget() {
	AddSectionImageDesktopArticleTarget.super.apply( this, arguments );
	AddSectionImageArticleTarget.call( this );
	// FIXME modify?
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new ImageSuggestionInteractionLogger( { is_mobile: false } );
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddSectionImageDesktopArticleTarget, StructuredTaskDesktopArticleTarget );
OO.mixinClass( AddSectionImageDesktopArticleTarget, AddSectionImageArticleTarget );

/**
 * Show the post-edit dialog if a null edit was made and the save dialog was not shown.
 * When the save dialog is shown, the post-edit dialog is shown after the save dialog is closed.
 */
AddSectionImageDesktopArticleTarget.prototype.onSaveDone = function () {
	if ( this.madeNullEdit && this.saveWithoutDialog ) {
		suggestedEditSession.setTaskState( SuggestedEditSession.static.STATES.SUBMITTED );
		suggestedEditSession.showPostEditDialog( { resetSession: true } );
	}
};

/** @override **/
AddSectionImageDesktopArticleTarget.prototype.prepareSaveWithoutShowingDialog = function () {
	const promise = ve.createDeferred(),
		$desktopLoadingOverlay = $( '<div>' ).addClass(
			// FIXME modify?
			'mw-ge-addImageDesktopArticleTarget-loadingOverlay',
		),
		$element = this.$element,
		$container = this.$container;
	this.saveWithoutDialog = true;
	$desktopLoadingOverlay.append( new OO.ui.ProgressBarWidget( {
		progress: false,
		// FIXME modify?
		classes: [ 'mw-ge-addImageDesktopArticleTarget-progressBar' ],
	} ).$element );
	// FIXME modify?
	$element.addClass( 'mw-ge-addImageDesktopArticleTarget--overlay-shown' );
	$container.append( $desktopLoadingOverlay );
	promise.then( () => {
		$desktopLoadingOverlay.remove();
		// FIXME modify?
		$element.removeClass( 'mw-ge-addImageDesktopArticleTarget--overlay-shown' );
	} );
	return promise;
};

module.exports = AddSectionImageDesktopArticleTarget;
