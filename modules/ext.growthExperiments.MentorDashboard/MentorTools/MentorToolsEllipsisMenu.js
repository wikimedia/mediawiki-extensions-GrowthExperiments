( function () {
	'use strict';

	/**
	 * @class MentorToolsEllipsisMenu
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} config Configuration object
	 */
	function MentorToolsEllipsisMenu( config ) {
		MentorToolsEllipsisMenu.super.call( this, config );

		this.menu = new OO.ui.ButtonMenuSelectWidget( {
			id: 'growthexperiments-mentor-dashboard-module-mentor-tools-cog-menu',
			icon: 'ellipsis',
			framed: false,
			menu: {
				horizontalPosition: 'end',
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 'quit',
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-cog-menu-quit' )
					} )
				]
			}
		} );
		this.menu.getMenu().connect( this, {
			choose: [ 'onMenuItemSelected' ]
		} );

		this.$element.html( this.menu.$element );
	}
	OO.inheritClass( MentorToolsEllipsisMenu, OO.ui.Widget );

	/**
	 * Process user selection
	 *
	 * This is called when an user picks an item from the menu.
	 *
	 * @param {string} option
	 */
	MentorToolsEllipsisMenu.prototype.onMenuItemSelected = function ( option ) {
		switch ( option.data ) {
			case 'quit':
				OO.ui.confirm(
					mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-cog-menu-quit-text' ),
					{
						title: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-cog-menu-quit-headline' )
					}
				).then( function ( confirmed ) {
					if ( confirmed ) {
						window.location.href = new mw.Title( 'Special:QuitMentorship' ).getUrl();
					}
				} );
				break;
			default:
				mw.log.error( 'MentorToolsEllipsisMenu: Unsupported operation.' );
				break;
		}
	};

	module.exports = MentorToolsEllipsisMenu;
}() );
