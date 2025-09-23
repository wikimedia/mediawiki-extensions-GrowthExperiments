const ImageSuggestionInteractionLogger = require( './ImageSuggestionInteractionLogger.js' ),
	StructuredTaskSaveDialog = require( '../StructuredTaskSaveDialog.js' ),
	IMAGE_PREVIEW_ASPECT_RATIO = 328 / 180;

/**
 * Mixin for code sharing between AddImageDesktopSaveDialog and AddImageMobileSaveDialog.
 * This is to solve the diamond inheritance problem of ve.ui.MWSaveDialog -->
 * AddImageSaveDialog and ve.ui.MWSaveDialog --> ve.ui.MWDesktopSaveDialog.
 *
 * This save dialog is only shown for accepted suggestion.
 *
 * @mixin mw.libs.ge.ui.AddImageSaveDialog
 * @extends ve.ui.MWSaveDialog
 * @mixes mw.libs.ge.ui.StructuredTaskSaveDialog
 *
 * @constructor
 */
function AddImageSaveDialog() {
	StructuredTaskSaveDialog.call( this );
	this.$element.addClass( 'mw-ge-addImageSaveDialog' );
	this.logger = new ImageSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'editsummary_dialog',
		/* eslint-enable camelcase */
	} );
	/** @property {mw.libs.ge.ImageRecommendationSummary} summaryData */
	this.summaryData = {};
}

OO.initClass( AddImageSaveDialog );
OO.mixinClass( AddImageSaveDialog, StructuredTaskSaveDialog );

/** @inheritDoc */
AddImageSaveDialog.prototype.initialize = function () {
	StructuredTaskSaveDialog.prototype.initialize.call( this );

	// Replace the save panel. The other panels are good as they are.
	this.savePanel.$element.empty();
	this.$summaryBody = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-body' );
	this.$summaryContent = $( '<div>' )
		.addClass( 'mw-ge-addImageSaveDialog-content' ).append( [
			this.getSummaryHeader(),
			this.$summaryBody,
		] );
	this.$copyrightFooter = $( '<p>' ).addClass(
		'mw-ge-addImageSaveDialog-copyrightWarning',
	).append(
		mw.message( 'growthexperiments-addimage-summary-copyrightwarning' ).parse(),
	);
	this.$copyrightFooter.find( 'a' ).attr( 'target', '_blank' );
	this.$watchlistFooter = $( '<div>' );

	this.savePanel.$element.append(
		this.$summaryContent,
		this.$watchlistFooter,
		this.$copyrightFooter,
	);
};

/**
 * Get the summary header element
 *
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getSummaryHeader = function () {
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-summaryLabel' ).text(
		mw.message( 'growthexperiments-addimage-summary-label' ).text(),
	);
};

/**
 * Update the summary body based on the summary data
 */
AddImageSaveDialog.prototype.updateSummaryBody = function () {
	this.$summaryBody.empty().append( [
		this.getImageSummary( this.summaryData.filename ),
		this.getAcceptedContent( this.summaryData ),
	] );
};

/**
 * Show the image file name along with its acceptance state
 *
 * @param {string} fileName Image file name
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getImageSummary = function ( fileName ) {
	const $imageInfo = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imageInfo' ).append( [
		new OO.ui.IconWidget( { icon: 'image' } ).$element,
		$( '<span>' )
			.addClass( 'mw-ge-addImageSaveDialog-imageInfo-filename' )
			.text( fileName ),
	] );
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imageSummary' ).append( [
		$imageInfo,
		new OO.ui.IconWidget( { icon: 'check' } ).$element,
	] );
};

/**
 * Get the image preview element
 *
 * @param {mw.libs.ge.ImageRecommendationSummary} summaryData
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getImagePreview = function ( summaryData ) {
	const AddImageUtils = require( './AddImageUtils.js' ),
		$imagePreview = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imagePreview' ),
		// Ideally this would be the width of the save dialog's content, but at this point the
		// element is not yet attached to the DOM (getReadyProcess would give us the element width
		// but the image preview would come in after the other elements are rendered).
		imageRenderData = AddImageUtils.getImageRenderData( summaryData.metadata, window );
	$imagePreview.css( {
		backgroundImage: 'url("' + imageRenderData.src + '")',
		// Maintain the aspect ratio of the image preview container
		paddingTop: ( 1 / IMAGE_PREVIEW_ASPECT_RATIO * 100 ) + '%',
	} );
	return $imagePreview;
};

/**
 * Get the content element for accepted image suggestion
 *
 * @param {mw.libs.ge.ImageRecommendationSummary} summaryData
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getAcceptedContent = function ( summaryData ) {
	const $caption = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-caption' );
	$caption.text( summaryData.caption );
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-bodyContent' ).append( [
		this.getImagePreview( summaryData ), $caption,
	] );
};

/** @inheritDoc */
AddImageSaveDialog.prototype.getSetupProcess = function ( data ) {
	return StructuredTaskSaveDialog.prototype.getSetupProcess.call( this, data ).next( function () {
		this.summaryData = ve.init.target.getSummaryData();
		this.updateSummaryBody();
		this.$watchlistFooter.empty();
		this.$watchlistFooter.append( this.getWatchlistCheckbox() );
		this.logger.log( 'impression', this.getLogMetadata() );
	}, this );
};

/** @override **/
AddImageSaveDialog.prototype.getLogMetadata = function () {
	return {
		/* eslint-disable camelcase */
		acceptance_state: 'accepted',
		caption_length: this.summaryData.caption.length,
		/* eslint-enable camelcase */
	};
};

module.exports = AddImageSaveDialog;
