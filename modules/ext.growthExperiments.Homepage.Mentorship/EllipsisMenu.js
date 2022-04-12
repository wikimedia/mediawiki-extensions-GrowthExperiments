( function () {
	'use strict';

	var MentorshipOptoutProcess = require( './MentorshipOptoutProcess.js' );

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
	}

	OO.inheritClass( EllipsisMenu, OO.ui.Widget );

	/**
	 * Process the choose event triggered by the ellipsis menu
	 *
	 * @param {Object} option Selected option (as passed by the choose event)
	 */
	EllipsisMenu.prototype.onMenuItemSelected = function ( option ) {
		switch ( option.data ) {
			case 'optout':
				this.optoutProcess.showOptoutDialog();
				break;
		}
	};

	module.exports = EllipsisMenu;
}() );
