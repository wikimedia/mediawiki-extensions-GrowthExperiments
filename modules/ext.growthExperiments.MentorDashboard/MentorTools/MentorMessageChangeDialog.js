'use strict';

/**
 * @class
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
		value: mw.config.get( 'GEMentorDashboardMentorIntroMessage' )
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
MentorMessageChangeDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;

	if ( action === 'save' ) {
		return new OO.ui.Process( function () {
			var newMessage = dialog.mentorMessageInput.getValue();

			return new mw.Api().postWithToken( 'csrf', {
				action: 'growthmanagementorlist',
				geaction: 'change',
				message: newMessage,
				autoassigned: mw.config.get( 'GEMentorDashboardMentorAutoAssigned' )
			} ).then( function () {
				mw.notify(
					mw.msg(
						'growthexperiments-mentor-dashboard-mentor-tools-message-change-dialog-success',
						newMessage
					),
					{ type: 'info' }
				);

				dialog.emit( 'messageset', newMessage );
				dialog.close( { action: action } );
			} );
		} );
	}

	return MentorMessageChangeDialog.super.prototype.getActionProcess.call( this, action );
};

module.exports = MentorMessageChangeDialog;
