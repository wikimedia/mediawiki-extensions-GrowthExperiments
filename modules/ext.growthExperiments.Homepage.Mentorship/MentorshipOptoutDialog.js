( function () {
	'use strict';

	/**
	 * @class mw.libs.ge.MentorshipOptoutDialog
	 * @extends OO.ui.MessageDialog
	 *
	 * @param {Object} config
	 * @constructor
	 */
	function MentorshipOptoutDialog( config ) {
		MentorshipOptoutDialog.super.call( this, config );
	}
	OO.inheritClass( MentorshipOptoutDialog, OO.ui.MessageDialog );

	MentorshipOptoutDialog.static.name = 'mentorshipOptoutDialog';
	MentorshipOptoutDialog.static.size = 'small';
	MentorshipOptoutDialog.static.title = mw.msg( 'growthexperiments-homepage-mentorship-optout-header' );
	MentorshipOptoutDialog.static.message = mw.message(
		'growthexperiments-homepage-mentorship-optout-text',
		mw.config.get( 'GEHomepageMentorshipMentorGender' ),
	).text();

	MentorshipOptoutDialog.static.actions = [
		{
			flags: 'safe',
			label: mw.msg( 'growthexperiments-homepage-mentorship-optout-cancel' ),
			action: 'cancel',
		},
		{
			flags: [ 'primary', 'destructive' ],
			label: mw.msg( 'growthexperiments-homepage-mentorship-optout-optout' ),
			action: 'done',
		},
	];

	/** @inheritDoc **/
	MentorshipOptoutDialog.prototype.getActionProcess = function ( action ) {
		if ( action === 'done' ) {
			this.emit( 'confirmation' );
		}

		return MentorshipOptoutDialog.super.prototype.getActionProcess.call( this, action );
	};

	module.exports = MentorshipOptoutDialog;

}() );
