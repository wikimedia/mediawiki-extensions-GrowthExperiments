var RejectionReasonSelect = require( '../RejectionReasonSelect.js' );

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
		action: 'cancel'
	},
	{
		flags: [ 'primary', 'progressive' ],
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-action-done' ).text(),
		action: 'done'
	}
];

/** @inheritDoc **/
RecommendedImageRejectionDialog.prototype.initialize = function () {
	RecommendedImageRejectionDialog.super.prototype.initialize.call( this );
	this.message.$element.addClass( 'oo-ui-inline-help' );
	var selectOptions = [ {
		data: 'not-relevant',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-notrelevant' ).text()
	}, {
		data: 'no-info',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-noinfo' ).text()
	}, {
		data: 'offensive',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-offensive' ).text()
	}, {
		data: 'low-quality',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-lowquality' ).text()
	}, {
		data: 'unfamiliar',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-unfamiliar' ).text()
	}, {
		data: 'foreign-language',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-foreignlanguage' ).text()
	}, {
		data: 'other',
		label: mw.message( 'growthexperiments-addimage-rejectiondialog-reason-other' ).text()
	} ];
	this.reasonSelect = new RejectionReasonSelect( {
		options: selectOptions,
		isMultiSelect: true
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
		var selectedItems = this.reasonSelect.findSelection();
		this.close( { action: action, reason: selectedItems } );
	}, this );
};

module.exports = RecommendedImageRejectionDialog;
