( function () {
	'use strict';

	const MentorshipOptoutProcess = require( './MentorshipOptoutProcess.js' ),
		MentorshipDetailsModal = require( './MentorshipDetailsModal.js' );

	/**
	 * @class mw.libs.ge.EllipsisMenu
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} config Configuration object
	 */
	function EllipsisMenu( config ) {
		EllipsisMenu.super.call( this, config );

		this.menu = new OO.ui.ButtonMenuSelectWidget( {
			icon: 'ellipsis',
			// TODO: Use a more verbose accessibility label?
			label: mw.msg( 'ellipsis' ),
			invisibleLabel: true,
			framed: false,
			menu: {
				horizontalPosition: 'end',
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 'about',
						label: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-about' ),
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 'optout',
						label: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-optout' ),
					} ),
				],
			},
		} );
		this.menu.getMenu().connect( this, {
			choose: [ 'onMenuItemSelected' ],
		} );

		this.$element.empty().append( this.menu.$element );

		this.windowManager = OO.ui.getWindowManager();
		this.optoutProcess = new MentorshipOptoutProcess(
			this.windowManager,
			config.mode,
		);

		this.mentorshipDetailsModal = new MentorshipDetailsModal();
		this.mentorshipDetailsModal.connect( this, {
			optout: [ 'showOptOutDialog' ],
		} );
		this.windowManager.addWindows( [
			this.mentorshipDetailsModal,
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

	let ellipsisMenu;
	/**
	 * @param {jQuery} $homepageContainer
	 * @return {mw.libs.ge.EllipsisMenu}
	 */
	module.exports = function ( $homepageContainer ) {
		if ( ellipsisMenu === undefined ) {
			ellipsisMenu = new EllipsisMenu( {
				mode: $homepageContainer.find( '.growthexperiments-homepage-module-mentorship' ).data( 'mode' ),
			} );
		}

		// connect to the #growthexperiments-homepage-mentorship-learn-more link, if available
		$homepageContainer.find( '#growthexperiments-homepage-mentorship-learn-more' ).on( 'click', ( e ) => {
			// Allow modified clicks / middle-click / open-in-new-tab to follow the real href (T312046).
			if ( e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return;
			}
			e.preventDefault();
			ellipsisMenu.showAboutMentorshipModal();
		} );

		// Open the about dialog when arriving via the learn-more URL (new tab / shared link).
		if ( mw.util.getParamValue( 'geMentorshipAbout' ) ) {
			ellipsisMenu.showAboutMentorshipModal();
		}

		// add the ellipsis menu to the page, if applicable
		const $ellipsis = $homepageContainer.find( '#mw-ge-homepage-mentorship-ellipsis' );
		if ( $ellipsis.length ) {
			$ellipsis.replaceWith( ellipsisMenu.$element );
		}

		return ellipsisMenu;
	};
}() );
