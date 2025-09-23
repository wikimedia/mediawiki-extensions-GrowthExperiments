const StructuredTaskMessageDialog = require( '../StructuredTaskMessageDialog.js' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

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

	/**
	 * Preference for showing caption onboarding the next time.
	 * This needs to stay in sync with the one defined in SuggestedEdits.php
	 *
	 * @property {string}
	 */
	this.CAPTION_ONBOARDING_PREF = 'growthexperiments-addimage-caption-onboarding';

	this.$element.addClass( [
		'mw-ge-addImageCaptionInfoDialog',
		OO.ui.isMobile() ?
			'mw-ge-addImageCaptionInfoDialog-mobile' :
			'mw-ge-addImageCaptionInfoDialog-desktop',
	] );
}

OO.inheritClass( AddImageCaptionInfoDialog, StructuredTaskMessageDialog );

AddImageCaptionInfoDialog.static.name = 'addImageCaptionInfo';

AddImageCaptionInfoDialog.static.title = mw.message(
	'growthexperiments-addimage-caption-info-dialog-title',
).text();

AddImageCaptionInfoDialog.static.size = OO.ui.isMobile() ? 'small' : 'medium';

AddImageCaptionInfoDialog.static.message = function () {
	const articleTitle = suggestedEditSession.getCurrentTitle().getNameText(),
		/** @type {mw.libs.ge.AddImageArticleTarget} **/
		articleTarget = ve.init.target,
		contentLanguageName = articleTarget.getSelectedSuggestion().metadata.contentLanguageName,
		$guidelines = $( '<ul>' ).addClass( 'mw-ge-addImageCaptionInfoDialog-list' ),
		guidelineItems = [
			mw.message(
				'growthexperiments-addimage-caption-info-dialog-guidelines-review',
			).parse(),
			mw.message(
				'growthexperiments-addimage-caption-info-dialog-guidelines-describe',
			).params( [ articleTitle ] ).parse(),
			mw.message(
				'growthexperiments-addimage-caption-info-dialog-guidelines-neutral',
			).parse(),
		];

	let languageGuideline;
	if ( contentLanguageName ) {
		languageGuideline = mw.message(
			'growthexperiments-addimage-caption-info-dialog-guidelines-language',
		).params( [ contentLanguageName ] ).parse();
	} else {
		languageGuideline = mw.message(
			'growthexperiments-addimage-caption-info-dialog-guidelines-language-generic',
		).parse();
	}
	guidelineItems.push( languageGuideline );
	guidelineItems.forEach( ( guidelineItemText ) => {
		$guidelines.append( $( '<li>' ).html( guidelineItemText ) );
	} );
	return $( '<div>' ).append( [
		mw.message( 'growthexperiments-addimage-caption-info-dialog-message' ).params(
			[ articleTitle ],
		).parse(),
		$guidelines,
	] );
};

AddImageCaptionInfoDialog.static.actions = [
	{
		action: 'accept',
		label: mw.message(
			'growthexperiments-addimage-caption-info-dialog-confirm',
		).text(),
	},
];

/**
 * Set up the don't show again checkbox based on the dialog opening data
 *
 * @param {Object} data
 * @param {boolean} [data.shouldShowDismissField] Whether the checkbox should be shown
 */
AddImageCaptionInfoDialog.prototype.setupDismissField = function ( data ) {
	const self = this,
		shouldShowDismissField = !!data.shouldShowDismissField;

	if ( this.dismissField ) {
		this.dismissField.toggle( shouldShowDismissField );
		return;
	}

	this.checkBoxInput = new OO.ui.CheckboxInputWidget( {
		selected: false,
		value: 'dismissCaptionOnboarding',
	} );
	this.checkBoxInput.on( 'change', ( isSelected ) => {
		new mw.Api().saveOption( self.CAPTION_ONBOARDING_PREF, isSelected ? '1' : '0' );
	} );
	// Set up the FieldLayout here instead of during initialization so that if the field doesn't
	// need to be shown at all during the flow, it's not set up
	this.dismissField = new OO.ui.FieldLayout( this.checkBoxInput, {
		label: mw.message(
			'growthexperiments-structuredtask-onboarding-dialog-dismiss-checkbox',
		).text(),
		align: 'inline',
		classes: [ 'mw-ge-addImageCaptionInfoDialog-dismiss-field' ],
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

/** @inheritDoc **/
AddImageCaptionInfoDialog.prototype.getActionProcess = function () {
	return new OO.ui.Process( function () {
		const closeData = {};
		if ( this.checkBoxInput ) {
			closeData.dialogDismissed = this.checkBoxInput.isSelected();
		}
		this.close( closeData );
	}, this );
};

module.exports = AddImageCaptionInfoDialog;
