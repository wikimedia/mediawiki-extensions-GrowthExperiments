const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskDesktopArticleTarget = require( '../StructuredTaskDesktopArticleTarget.js' ),
	SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
	suggestedEditSession = SuggestedEditSession.getInstance(),
	AddImageArticleTarget = StructuredTask.addImage().AddImageArticleTarget,
	ImageSuggestionInteractionLogger = StructuredTask.addImage().ImageSuggestionInteractionLogger;

/**
 * Desktop version of AddImageArticleTarget
 *
 * @class mw.libs.ge.AddImageDesktopArticleTarget
 * @extends mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @mixes mw.libs.ge.AddImageArticleTarget
 * @constructor
 */
function AddImageDesktopArticleTarget() {
	AddImageDesktopArticleTarget.super.apply( this, arguments );
	AddImageArticleTarget.call( this );
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new ImageSuggestionInteractionLogger( { is_mobile: false } );
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddImageDesktopArticleTarget, StructuredTaskDesktopArticleTarget );
OO.mixinClass( AddImageDesktopArticleTarget, AddImageArticleTarget );

/**
 * Show the post-edit dialog if a null edit was made and the save dialog was not shown.
 * When the save dialog is shown, the post-edit dialog is shown after the save dialog is closed.
 */
AddImageDesktopArticleTarget.prototype.onSaveDone = function () {
	if ( this.madeNullEdit && this.saveWithoutDialog ) {
		suggestedEditSession.setTaskState( SuggestedEditSession.static.STATES.SUBMITTED );
		suggestedEditSession.showPostEditDialog( { resetSession: true } );
	}
};

/** @override **/
AddImageDesktopArticleTarget.prototype.prepareSaveWithoutShowingDialog = function () {
	const promise = ve.createDeferred(),
		$desktopLoadingOverlay = $( '<div>' ).addClass(
			'mw-ge-addImageDesktopArticleTarget-loadingOverlay',
		),
		$element = this.$element,
		$container = this.$container;
	this.saveWithoutDialog = true;
	$desktopLoadingOverlay.append( new OO.ui.ProgressBarWidget( {
		progress: false,
		classes: [ 'mw-ge-addImageDesktopArticleTarget-progressBar' ],
	} ).$element );
	$element.addClass( 'mw-ge-addImageDesktopArticleTarget--overlay-shown' );
	$container.append( $desktopLoadingOverlay );
	promise.then( () => {
		$desktopLoadingOverlay.remove();
		$element.removeClass( 'mw-ge-addImageDesktopArticleTarget--overlay-shown' );
	} );
	return promise;
};

module.exports = AddImageDesktopArticleTarget;
