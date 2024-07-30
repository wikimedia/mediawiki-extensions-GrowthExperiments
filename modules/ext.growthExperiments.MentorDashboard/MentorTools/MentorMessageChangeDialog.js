'use strict';

/**
 * @class mw.libs.ge.MentorMessageChangeDialog
 *
 * @param {Object} config
 * @constructor
 */
function MentorMessageChangeDialog( config ) {
	MentorMessageChangeDialog.super.call( this, config );
}
OO.inheritClass( MentorMessageChangeDialog, OO.ui.ProcessDialog );

MentorMessageChangeDialog.static.name = 'mentorMessageChangeDialog';
MentorMessageChangeDialog.static.title = mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-message-change-dialog-title' );
MentorMessageChangeDialog.static.actions = [
	{
		action: 'save',
		label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-message-change-dialog-save' ),
		flags: [ 'primary', 'progressive' ]
	},
	{
		label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-message-change-dialog-cancel' ),
		flags: 'safe'
	}
];

/** @inheritDoc **/
MentorMessageChangeDialog.prototype.initialize = function () {
	MentorMessageChangeDialog.super.prototype.initialize.apply( this, arguments );

	this.mentorMessageInput = new OO.ui.TextInputWidget( {
		value: mw.config.get( 'GEMentorDashboardMentorIntroMessage' ),
		maxLength: mw.config.get( 'GEMentorDashboardMentorIntroMessageMaxLength' )
	} );
	this.mentorMessageInput.connect( this, {
		change: [ 'updateRemainingMessageLength' ]
	} );

	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.content.$element.append(
		new OO.ui.FieldLayout( this.mentorMessageInput, {
			align: 'top',
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-message-change-dialog-message-label' )
		} ).$element
	);
	this.$body.append( this.content.$element );
};

/** @inheritDoc **/
MentorMessageChangeDialog.prototype.getSetupProcess = function () {
	return MentorMessageChangeDialog.super.prototype.getSetupProcess.apply( this, arguments )
		// has to be in getSetupProcess(); when executed inside initialize,
		// label is covered by the field content
		.next( this.updateRemainingMessageLength.bind( this ) );
};

/** @inheritDoc **/
MentorMessageChangeDialog.prototype.getActionProcess = function ( action ) {
	const dialog = this;

	if ( action === 'save' ) {
		return new OO.ui.Process( () => {
			const newMessage = dialog.mentorMessageInput.getValue();

			return new mw.Api().postWithToken( 'csrf', {
				action: 'growthmanagementorlist',
				geaction: 'change',
				message: newMessage
			} ).then( () => {
				mw.notify(
					mw.msg(
						'growthexperiments-mentor-dashboard-mentor-tools-message-change-dialog-success',
						newMessage
					),
					{ type: 'info' }
				);

				dialog.emit( 'messageset', newMessage );
				dialog.close( { action: action } );
			} ).catch( ( errorCode, data ) => {
				mw.notify(
					data.error.info,
					{ type: 'error' }
				);
				dialog.close( { action: action } );
			} );
		} );
	}

	return MentorMessageChangeDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * Update remaining message length in the mentor message input field
 */
MentorMessageChangeDialog.prototype.updateRemainingMessageLength = function () {
	this.mentorMessageInput.setLabel( String(
		mw.config.get( 'GEMentorDashboardMentorIntroMessageMaxLength' ) - this.mentorMessageInput.getInputLength()
	) );
};

module.exports = MentorMessageChangeDialog;
