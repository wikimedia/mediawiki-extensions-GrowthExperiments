( function () {
	'use strict';

	function MentorshipDetailsModal( config ) {
		MentorshipDetailsModal.super.call( this, Object.assign( {}, config, {
			size: 'medium',
		} ) );
	}
	OO.inheritClass( MentorshipDetailsModal, OO.ui.ProcessDialog );

	MentorshipDetailsModal.static.name = 'mentorshipDetailsModal';
	MentorshipDetailsModal.static.title = mw.msg( 'growthexperiments-homepage-mentorship-about-header' );
	MentorshipDetailsModal.static.actions = [
		{
			action: 'done',
			label: mw.msg( 'growthexperiments-homepage-mentorship-about-done' ),
			flags: [ 'primary', 'progressive' ],
		},
	];

	MentorshipDetailsModal.prototype.onOptoutButtonClicked = function () {
		const dialog = this;
		setTimeout( () => {
			dialog.emit( 'optout' );
		}, 100 );

		this.close( { action: 'done' } );
	};

	MentorshipDetailsModal.prototype.initialize = function () {
		MentorshipDetailsModal.super.prototype.initialize.call( this );

		const optoutBtn = new OO.ui.ButtonWidget( {
			label: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-optout' ),
		} );
		optoutBtn.connect( this, {
			click: [ 'onOptoutButtonClicked' ],
		} );

		const mentorGender = mw.config.get( 'GEHomepageMentorshipMentorGender' );
		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		this.content.$element.append(
			$( '<div>' )
				.addClass( 'growthexperiments-homepage-mentorship-about-mentorship' )
				.append(
					$( '<h4>' ).text(
						mw.message( 'growthexperiments-homepage-mentorship-about-subheader-mentor', mentorGender ).text(),
					),
					$( '<p>' ).text(
						mw.message( 'growthexperiments-homepage-mentorship-about-mentor-par1', mentorGender ).text(),
					),
					$( '<p>' ).text(
						mw.message( 'growthexperiments-homepage-mentorship-about-mentor-par2', mentorGender ).text(),
					),
					$( '<h4>' ).text( mw.msg( 'growthexperiments-homepage-mentorship-about-subheader-optout' ) ),
					$( '<p>' ).text( mw.msg( 'growthexperiments-homepage-mentorship-about-optout-par1' ) ),
					optoutBtn.$element,
				),
		);
		this.$body.append( this.content.$element );
	};

	MentorshipDetailsModal.prototype.getActionProcess = function ( action ) {
		const dialog = this;

		return new OO.ui.Process( () => {
			dialog.close( { action: action } );
		} );
	};
	module.exports = MentorshipDetailsModal;
}() );
