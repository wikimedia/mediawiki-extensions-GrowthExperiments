var SelectWithTextInputWidget = require( '../../ui-components/SelectWithTextInputWidget.js' );

/**
 * Dialog with a list of reasons for rejecting a suggestions
 *
 * @class mw.libs.ge.RecommendedLinkRejectionDialog
 * @extends OO.ui.MessageDialog
 * @param {Object} config
 * @constructor
 */
function RecommendedLinkRejectionDialog( config ) {
	RecommendedLinkRejectionDialog.super.call( this, config );

	this.$element.addClass( 'mw-ge-recommendedLinkRejectionDialog' );
}

OO.inheritClass( RecommendedLinkRejectionDialog, OO.ui.MessageDialog );

RecommendedLinkRejectionDialog.static.name = 'recommendedLinkRejection';
RecommendedLinkRejectionDialog.static.size = OO.ui.isMobile() ? 'small' : 'medium';
RecommendedLinkRejectionDialog.static.title = function () {
	return mw.message( 'growthexperiments-addlink-rejectiondialog-title' ).parseDom();
};
RecommendedLinkRejectionDialog.static.message = mw.msg( 'growthexperiments-addlink-rejectiondialog-message', mw.user.getName() );
RecommendedLinkRejectionDialog.static.actions = [
	{
		action: 'done',
		label: mw.msg( 'growthexperiments-addlink-rejectiondialog-action-done' ),
		flags: [ 'progressive' ]
	}
];

/** @inheritDoc **/
RecommendedLinkRejectionDialog.prototype.initialize = function () {
	// Parent method
	RecommendedLinkRejectionDialog.super.prototype.initialize.call( this );
	this.message.$element.addClass( 'oo-ui-inline-help' );
	var selectOptions = [ {
		data: 'everyday',
		label: mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-everyday' )
	}, {
		data: 'wrong-target',
		label: $( '<span>' ).append(
			$( '<span>' )
				.addClass( 'mw-ge-recommendedLinkRejectionDialog-reason-wrong-target-label' )
				.text( mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-wrong-target' ) )
		),
		classes: [ 'mw-ge-recommendedLinkRejectionDialog-reason-wrong-target' ]
	}, {
		data: 'more-fewer-words',
		label: mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-more-fewer-words' )
	}, {
		data: 'other',
		label: mw.msg( 'growthexperiments-addlink-rejectiondialog-reason-other' ),
		hasTextInput: mw.config.get( 'wgGEStructuredTaskRejectionReasonTextInputEnabled' ),
		textInputPlaceholder: mw.message( 'growthexperiments-structuredtask-other-rejectionreason-placeholder' ).text(),
		textInputMaxLength: 100
	} ];
	this.reasonSelect = new SelectWithTextInputWidget( {
		options: selectOptions,
		isMultiSelect: true
	} );
	this.text.$element.append( this.reasonSelect.getElement() );
};

/** @inheritDoc **/
RecommendedLinkRejectionDialog.prototype.getSetupProcess = function ( data ) {
	return RecommendedLinkRejectionDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.reasonSelect.updateSelection( data.selection );
			this.reasonSelect.updateTextInputValueForData( 'other', data.otherRejectionReason );
		}, this );
};

/** @inheritDoc **/
RecommendedLinkRejectionDialog.prototype.getActionProcess = function ( action ) {
	// Handle Esc key + anything unforeseen.
	if ( action !== 'done' ) {
		action = 'cancel';
	}

	return new OO.ui.Process( function () {
		var selectedItems = this.reasonSelect.findSelection();
		this.close( {
			action: action,
			reason: selectedItems,
			otherRejectionReason: this.reasonSelect.getTextInputValueForData( 'other' )
		} );
	}, this );
};

module.exports = RecommendedLinkRejectionDialog;
