( function () {
	'use strict';

	var MentorshipOptoutProcess = require( './MentorshipOptoutProcess.js' ),
		MentorshipDetailsModal = require( './MentorshipDetailsModal.js' );

	/**
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} config Configuration object
	 */
	function EllipsisMenu( config ) {
		EllipsisMenu.super.call( this, config );

		this.menu = new OO.ui.ButtonMenuSelectWidget( {
			icon: 'ellipsis',
			framed: false,
			menu: {
				horizontalPosition: 'end',
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 'about',
						label: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-about' )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 'optout',
						label: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-optout' )
					} )
				]
			}
		} );
		this.menu.getMenu().connect( this, {
			choose: [ 'onMenuItemSelected' ]
		} );

		this.$element.html( this.menu.$element );

		this.windowManager = OO.ui.getWindowManager();
		this.optoutProcess = new MentorshipOptoutProcess(
			this.windowManager,
			config.mode
		);

		this.mentorshipDetailsModal = new MentorshipDetailsModal();
		this.mentorshipDetailsModal.connect( this, {
			optout: [ 'showOptOutDialog' ]
		} );
		this.windowManager.addWindows( [
			this.mentorshipDetailsModal
		] );
	}

	OO.inheritClass( EllipsisMenu, OO.ui.Widget );

	/**
	 * Process the choose event triggered by the ellipsis menu
	 *
	 * @param {Object} option Selected option (as passed by the choose event)
	 */
	EllipsisMenu.prototype.onMenuItemSelected = function ( option ) {
		switch ( option.data ) {
			case 'about':
				this.showAboutMentorshipModal();
				break;
			case 'optout':
				this.showOptOutDialog();
				break;
		}
	};

	EllipsisMenu.prototype.showAboutMentorshipModal = function () {
		this.windowManager.openWindow( this.mentorshipDetailsModal );
	};

	/**
	 * Open the mentorship opt out dialog.
	 *
	 * Exists as a separate method to be available as an event handler.
	 */
	EllipsisMenu.prototype.showOptOutDialog = function () {
		this.optoutProcess.showOptoutDialog();
	};

	module.exports = EllipsisMenu;
}() );
