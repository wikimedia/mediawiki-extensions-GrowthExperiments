var ImageSuggestionInteractionLogger = require( './ImageSuggestionInteractionLogger.js' ),
	StructuredTaskSaveDialog = require( '../StructuredTaskSaveDialog.js' );

/**
 * Mixin for code sharing between AddImageDesktopSaveDialog and AddImageMobileSaveDialog.
 * This is to solve the diamond inheritance problem of ve.ui.MWSaveDialog -->
 * AddImageSaveDialog and ve.ui.MWSaveDialog --> ve.ui.MWDesktopSaveDialog.
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
		active_interface: 'editsummary_dialog'
		/* eslint-enable camelcase */
	} );
	mw.config.set( 'wgEditSubmitButtonLabelPublish', true );
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
			this.$summaryBody
		] );
	this.$copyrightFooter = $( '<p>' ).addClass(
		'mw-ge-addImageSaveDialog-copyrightWarning'
	).append(
		mw.message( 'growthexperiments-addimage-summary-copyrightwarning' ).parse()
	);
	this.$copyrightFooter.find( 'a' ).attr( 'target', '_blank' );

	this.savePanel.$element.append(
		this.$summaryContent,
		this.$copyrightFooter
	);
};

/**
 * Get the summary header element
 *
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getSummaryHeader = function () {
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-summaryLabel' ).text(
		mw.message( 'growthexperiments-addimage-summary-label' ).text()
	);
};

/**
 * Update the summary body based on the specified data
 *
 * @param {mw.libs.ge.ImageRecommendationSummary} summaryData
 */
AddImageSaveDialog.prototype.updateSummaryBody = function ( summaryData ) {
	var accepted = summaryData.accepted;
	this.$summaryBody.empty().append( [
		this.getImageSummary( summaryData.filename, accepted ),
		accepted ? this.getAcceptedContent( summaryData ) : this.getRejectedContent()
	] );
};

/**
 * Show the image file name along with its acceptance state
 *
 * @param {string} fileName Image file name
 * @param {boolean} accepted Whether the image suggested is accepted
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getImageSummary = function ( fileName, accepted ) {
	var $imageInfo = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imageInfo' ).append( [
		new OO.ui.IconWidget( { icon: 'image' } ).$element,
		$( '<span>' ).text( fileName )
	] );
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imageSummary' ).append( [
		$imageInfo,
		new OO.ui.IconWidget( { icon: accepted ? 'check' : 'close' } ).$element
	] );
};

/**
 * Get the content element for rejected image suggestion
 *
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getRejectedContent = function () {
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-bodyContent' ).text(
		mw.message( 'growthexperiments-addimage-summary-reject-description' ).text()
	);
};

/**
 * Get the content element for accepted image suggestion
 *
 * @param {mw.libs.ge.ImageRecommendationSummary} summaryData
 * @return {jQuery}
 */
AddImageSaveDialog.prototype.getAcceptedContent = function ( summaryData ) {
	var $imagePreview = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-imagePreview' ),
		$caption = $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-caption' );
	$imagePreview.css( 'background-image', 'url(' + summaryData.thumbUrl + ')' );
	$caption.text( summaryData.caption );
	return $( '<div>' ).addClass( 'mw-ge-addImageSaveDialog-bodyContent' ).append( [
		$imagePreview, $caption
	] );
};

/** @inheritDoc */
AddImageSaveDialog.prototype.getSetupProcess = function ( data ) {
	return StructuredTaskSaveDialog.prototype.getSetupProcess.call( this, data ).next( function () {
		this.updateSummaryBody( ve.init.target.getSummaryData() );
		// Edit summary will be localized in the content language via FormatAutocomments hook
		this.editSummaryInput.setValue( '/* growthexperiments-addimage-summary-summary: 1 */' );
		this.addToWatchlist();
	}, this );
};

module.exports = AddImageSaveDialog;
