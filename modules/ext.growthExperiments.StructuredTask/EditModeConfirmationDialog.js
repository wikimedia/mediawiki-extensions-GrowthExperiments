/**
 * Dialog confirming whether to switch away from machine suggestions mode
 *
 * @class mw.libs.ge.EditModeConfirmationDialog
 * @param {Object} config Configuration options
 * @extends OO.ui.MessageDialog
 * @constructor
 */
function EditModeConfirmationDialog( config ) {
	EditModeConfirmationDialog.super.call( this, config );

	this.$element.addClass( [
		'mw-ge-edit-mode-confirmation-dialog',
		OO.ui.isMobile() ?
			'mw-ge-edit-mode-confirmation-dialog-mobile' :
			'mw-ge-edit-mode-confirmation-dialog-desktop',
	] );
}

OO.inheritClass( EditModeConfirmationDialog, OO.ui.MessageDialog );

EditModeConfirmationDialog.static.name = 'editModeConfirmation';
EditModeConfirmationDialog.static.size = 'small';

EditModeConfirmationDialog.static.title = mw.message(
	'growthexperiments-structuredtask-editmode-confirmation-dialog-title',
).text();

EditModeConfirmationDialog.static.message = mw.message(
	'growthexperiments-structuredtask-editmode-confirmation-dialog-message', mw.user.getName(),
).text();

EditModeConfirmationDialog.static.actions = [
	{
		action: 'confirm',
		label: mw.message(
			'growthexperiments-structuredtask-editmode-confirmation-dialog-action-confirm',
		).text(),
	},
	{
		action: 'cancel',
		label: mw.message(
			'growthexperiments-structuredtask-editmode-confirmation-dialog-action-cancel',
		).text(),
	},
];

/**
 * Close the dialog when the user either confirms or cancels the switch
 *
 * @override
 */
EditModeConfirmationDialog.prototype.getActionProcess = function ( action ) {
	return new OO.ui.Process( function () {
		this.close( { isConfirm: action === 'confirm' } );
	}, this );
};

module.exports = EditModeConfirmationDialog;
