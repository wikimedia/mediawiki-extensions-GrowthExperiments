var StructuredTaskMessageDialog = require( '../StructuredTaskMessageDialog.js' ),
	// This needs to stay in sync with the one defined in SuggestedEdits.php
	ADD_IMAGE_CAPTION_ONBOARDING_PREF = 'growthexperiments-addimage-caption-onboarding';

/**
 * Dialog with guidance for Add an Image's caption step.
 * This dialog is shown:
 *  - automatically when the user first accepts the image suggestion and hasn't dismissed the dialog
 *  - manually when the user clicks on the help button during caption step.
 *
 * @class mw.libs.ge.AddImageCaptionInfoDialog
 * @extends mw.libs.ge.StructuredTaskMessageDialog
 *
 * @constructor
 */
function AddImageCaptionInfoDialog() {
	AddImageCaptionInfoDialog.super.apply( this, arguments );
	this.$element.addClass( 'mw-ge-addImageCaptionInfoDialog' );
}

OO.inheritClass( AddImageCaptionInfoDialog, StructuredTaskMessageDialog );

AddImageCaptionInfoDialog.static.name = 'addImageCaptionInfo';

AddImageCaptionInfoDialog.static.title = mw.message(
	'growthexperiments-addimage-caption-info-dialog-title'
).text();

AddImageCaptionInfoDialog.static.message = function () {
	return $( '<div>' ).append( [
		mw.message( 'growthexperiments-addimage-caption-info-dialog-message' ).escaped(),
		$( '<ul>' ).addClass( 'mw-ge-addImageCaptionInfoDialog-list' ).html(
			mw.message( 'growthexperiments-addimage-caption-info-dialog-guidelines' ).parse()
		)
	] );
};

AddImageCaptionInfoDialog.static.actions = [
	{
		action: 'accept',
		label: mw.message(
			'growthexperiments-addimage-caption-info-dialog-confirm'
		).text()
	}
];

/**
 * Set up the don't show again checkbox based on the dialog opening data
 *
 * @param {Object} data
 * @param {boolean} [data.shouldShowDismissField] Whether the checkbox should be shown
 */
AddImageCaptionInfoDialog.prototype.setupDismissField = function ( data ) {
	var shouldShowDismissField = !!data.shouldShowDismissField;
	if ( this.dismissField ) {
		this.dismissField.toggle( shouldShowDismissField );
		return;
	}

	var checkBoxInput = new OO.ui.CheckboxInputWidget( {
		selected: false,
		value: 'dismissCaptionOnboarding'
	} );
	checkBoxInput.on( 'change', function ( isSelected ) {
		new mw.Api().saveOption( ADD_IMAGE_CAPTION_ONBOARDING_PREF, isSelected ? '1' : '0' );
	} );
	// Set up the FieldLayout here instead of during initialization so that if the field doesn't
	// need to be shown at all during the flow, it's not set up
	this.dismissField = new OO.ui.FieldLayout( checkBoxInput, {
		label: mw.message(
			'growthexperiments-structuredtask-onboarding-dialog-dismiss-checkbox'
		).text(),
		align: 'inline',
		classes: [ 'mw-ge-addImageCaptionInfoDialog-dismiss-field' ]
	} );
	this.text.$element.append( this.dismissField.$element );
	this.dismissField.toggle( shouldShowDismissField );
};

/** @inheritDoc **/
AddImageCaptionInfoDialog.prototype.getSetupProcess = function ( data ) {
	return AddImageCaptionInfoDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.setupDismissField( data || {} );
		}, this );
};

module.exports = AddImageCaptionInfoDialog;
