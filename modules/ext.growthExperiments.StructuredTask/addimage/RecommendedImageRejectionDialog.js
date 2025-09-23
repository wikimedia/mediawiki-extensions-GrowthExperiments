const AdaptiveSelectWidget = require( '../../ui-components/AdaptiveSelectWidget.js' );

/**
 * Dialog with a list of reasons for rejecting a suggestions
 *
 * @class mw.libs.ge.RecommendedImageRejectionDialog
 * @extends OO.ui.MessageDialog
 * @param {Object} config
 * @constructor
 */
function RecommendedImageRejectionDialog( config ) {
	RecommendedImageRejectionDialog.super.call( this, config );

	this.$element.addClass( 'mw-ge-recommendedLinkRejectionDialog' );
}

OO.inheritClass( RecommendedImageRejectionDialog, OO.ui.MessageDialog );

RecommendedImageRejectionDialog.static.name = 'recommendedImageRejection';
RecommendedImageRejectionDialog.static.size = OO.ui.isMobile() ? 'small' : 'medium';
RecommendedImageRejectionDialog.static.title =
	mw.message( 'growthexperiments-addimage-rejectiondialog-header' ).text();
RecommendedImageRejectionDialog.static.message =
	mw.message( 'growthexperiments-addimage-rejectiondialog-subheader' ).text();
RecommendedImageRejectionDialog.static.actions = [
	{
		flags: 'safe',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-action-cancel' ).text(),
		action: 'cancel',
	},
	{
		flags: [ 'primary', 'progressive' ],
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-action-done' ).text(),
		action: 'done',
	},
];
/**
 * List of valid reasons for rejecting an image. Keep in sync with
 * AddImageSubmissionHandler::REJECTION_REASONS.
 *
 * @type {string[]}
 */
RecommendedImageRejectionDialog.static.rejectionReasons = [
	'notrelevant', 'noinfo', 'offensive', 'lowquality', 'unfamiliar', 'foreignlanguage', 'other',
];

/** @inheritDoc **/
RecommendedImageRejectionDialog.prototype.initialize = function () {
	RecommendedImageRejectionDialog.super.prototype.initialize.call( this );
	this.message.$element.addClass( 'oo-ui-inline-help' );
	const selectOptions = this.constructor.static.rejectionReasons.map( ( reason ) => ( {
		data: reason,
		// Messages used:
		// * growthexperiments-addimage-rejectiondialog-reason-notrelevant
		// * growthexperiments-addimage-rejectiondialog-reason-noinfo
		// * growthexperiments-addimage-rejectiondialog-reason-offensive
		// * growthexperiments-addimage-rejectiondialog-reason-lowquality
		// * growthexperiments-addimage-rejectiondialog-reason-unfamiliar
		// * growthexperiments-addimage-rejectiondialog-reason-foreignlanguage
		// * growthexperiments-addimage-rejectiondialog-reason-other
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-' + reason ).text(),
	} ) );
	this.reasonSelect = new AdaptiveSelectWidget( {
		options: selectOptions,
		isMultiSelect: true,
	} );
	this.text.$element.append( this.reasonSelect.getElement() );
};

/** @inheritDoc **/
RecommendedImageRejectionDialog.prototype.getSetupProcess = function ( data ) {
	return RecommendedImageRejectionDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.reasonSelect.updateSelection( data );
		}, this );
};

/** @inheritDoc **/
RecommendedImageRejectionDialog.prototype.getActionProcess = function ( action ) {
	// Handle Esc key + anything unforeseen.
	if ( action !== 'done' ) {
		action = 'cancel';
	}

	return new OO.ui.Process( function () {
		const selectedItems = ( action === 'cancel' ) ? [] : this.reasonSelect.findSelection();
		this.close( {
			action: action,
			reasons: selectedItems,
		} );
	}, this );
};

module.exports = RecommendedImageRejectionDialog;
