var RecommendedImageToolbarDialog = require( '../addimage/RecommendedImageToolbarDialog.js' );

/**
 * @class mw.libs.ge.RecommendedSectionImageToolbarDialog
 * @extends mw.libs.ge.RecommendedImageToolbarDialog
 * @constructor
 */
function RecommendedSectionImageToolbarDialog() {
	RecommendedSectionImageToolbarDialog.super.apply( this, arguments );
	this.$element.addClass( 'mw-ge-recommendedSectionImageToolbarDialog' );
}
OO.inheritClass( RecommendedSectionImageToolbarDialog, RecommendedImageToolbarDialog );

// No need to override the dialog name, we just make sure not to register the parent.

/** @inheritDoc **/
RecommendedSectionImageToolbarDialog.prototype.initialize = function () {
	RecommendedSectionImageToolbarDialog.super.prototype.initialize.call( this );
	this.$head.find( '.mw-ge-recommendedImageToolbarDialog-title' ).text(
		mw.message( 'growthexperiments-addsectionimage-inspector-title' ).text()
	);
	// We'll update the CTA during afterSetupProcess() as we need the recommendation data
};

/** @inheritDoc **/
RecommendedSectionImageToolbarDialog.prototype.afterSetupProcess = function () {
	this.images = this.getArticleTarget().images;
	this.updateCta();
	RecommendedSectionImageToolbarDialog.super.prototype.afterSetupProcess.call( this );
};

/**
 * Override the CTA text to include the section.
 */
RecommendedSectionImageToolbarDialog.prototype.updateCta = function () {
	this.$body.find( '.mw-ge-recommendedImageToolbarDialog-addImageCta' ).text(
		mw.message( 'growthexperiments-addsectionimage-inspector-cta' ).params( [
			this.images[ this.currentIndex ].visibleSectionTitle
		] ).text()
	);
};

module.exports = RecommendedSectionImageToolbarDialog;
