/* eslint-disable no-jquery/no-global-selector */
( function () {
	'use strict';

	var AwaySettingsDialog = require( './AwaySettingsDialog.js' );

	/**
	 * @class
	 *
	 * @constructor
	 * @param {Object} $body
	 */
	function MentorTools( $body ) {
		this.$body = $body;
		this.$body.prepend(
			new OO.ui.PopupButtonWidget( {
				icon: 'info',
				id: 'growthexperiments-mentor-dashboard-module-mentor-tools-info-icon',
				framed: false,
				invisibleLabel: true,
				popup: {
					icon: 'info',
					align: 'backwards',
					head: true,
					padded: true,
					label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-info-headline' ),
					$content: $( '<p>' ).append(
						mw.message( 'growthexperiments-mentor-dashboard-mentor-tools-info-text' ).parse()
					)
				}
			} ).$element
		);

		this.mentorStatusDropdown = new OO.ui.DropdownWidget( {
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-active' ),
			id: 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-dropdown',
			menu: {
				items: [
					new OO.ui.MenuOptionWidget( {
						icon: 'check',
						data: 'active',
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-active' )
					} ),
					new OO.ui.MenuOptionWidget( {
						icon: 'history',
						data: 'away',
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-away' )
					} )
				]
			}
		} );
		var itemToSelect = this.mentorStatusDropdown.getMenu().findItemFromData(
			$( '#growthexperiments-mentor-dashboard-mentor-tools-mentor-status-dropdown select' ).val()
		);
		this.mentorStatusDropdown.getMenu().selectItem( itemToSelect );
		this.mentorStatusDropdown.setIcon( itemToSelect.getIcon() );
		this.mentorStatusDropdown.getMenu().connect( this, {
			choose: [ 'onMentorStatusDropdownChanged' ]
		} );
		$( '#growthexperiments-mentor-dashboard-mentor-tools-mentor-status-dropdown' ).replaceWith(
			this.mentorStatusDropdown.$element
		);

		this.$mentorAwayMessage = $( '#growthexperiments-mentor-dashboard-module-mentor-tools-status-away-message' );

		this.$body.find( '.growthexperiments-mentor-dashboard-module-mentor-tools-mentor-weight' ).prepend(
			new OO.ui.PopupButtonWidget( {
				icon: 'info',
				id: 'growthexperiments-mentor-dashboard-module-mentor-tools-mentor-weight-info-icon',
				framed: false,
				invisibleLabel: true,
				popup: {
					padded: true,
					$content: $( '<div>' ).append(
						$( '<p>' ).html( mw.message( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-info-text-line1' ).parse() ),
						$( '<p>' ).html( mw.message( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-info-text-line2' ).parse() )
					)
				}
			} ).$element
		);
		this.mentorWeightDropdown = new OO.ui.DropdownWidget( {
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-medium' ),
			id: 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-dropdown',
			menu: {
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 1,
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-low' )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 2,
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-medium' )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 4,
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-high' )
					} )
				]
			}
		} );
		this.mentorWeightDropdown.getMenu().selectItem( this.mentorWeightDropdown.getMenu().findItemFromData(
			Number( $( '#growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-dropdown select' ).val() )
		) );
		this.mentorWeightDropdown.getMenu().connect( this, {
			choose: [ 'onMentorWeightDropdownChanged' ]
		} );
		$( '#growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-dropdown' ).replaceWith(
			this.mentorWeightDropdown.$element
		);

		this.windowManager = new OO.ui.WindowManager();
		this.$body.append( this.windowManager.$element );

		this.awaySettingsDialog = new AwaySettingsDialog();
		this.awaySettingsDialog.connect( this, {
			awayset: [ 'onMentorBackTimestampChanged' ],
			cancel: [ 'onAwaySettingsDialogCancelled' ]
		} );
		this.windowManager.addWindows( [ this.awaySettingsDialog ] );
	}

	MentorTools.prototype.onMentorStatusDropdownChanged = function () {
		var selectedItem = this.mentorStatusDropdown.getMenu().findSelectedItem();
		this.mentorStatusDropdown.setIcon( selectedItem.getIcon() );

		if ( selectedItem.getData() === 'away' ) {
			this.windowManager.openWindow( this.awaySettingsDialog );
		} else if ( selectedItem.getData() === 'active' ) {
			var mentorTools = this;

			new mw.Api().postWithToken( 'csrf', {
				action: 'growthsetmentorstatus',
				gesstatus: 'active'
			} ).then( function () {
				mw.notify(
					mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-changed-to-active' ),
					{ type: 'info' }
				);
				mentorTools.onMentorBackTimestampChanged( null );
			} ).catch( function () {
				mw.notify(
					mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-error-unknown' ),
					{ type: 'error' }
				);
			} );
		}
	};

	MentorTools.prototype.onMentorWeightDropdownChanged = function () {
		var selectedItem = this.mentorWeightDropdown.getMenu().findSelectedItem();

		new mw.Api().postWithToken( 'csrf', {
			action: 'options',
			optionname: 'growthexperiments-mentorship-weight',
			optionvalue: selectedItem.getData()
		} ).then( function () {
			mw.notify(
				mw.msg(
					'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-changed',
					selectedItem.getLabel()
				),
				{ type: 'info' }
			);
		} ).catch( function () {
			mw.notify(
				mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-error-unknown' ),
				{ type: 'error' }
			);
		} );
	};

	MentorTools.prototype.onAwaySettingsDialogCancelled = function () {
		var itemToSelect = this.mentorStatusDropdown.getMenu().findItemFromData( 'active' );
		this.mentorStatusDropdown.getMenu().selectItem( itemToSelect );
		this.mentorStatusDropdown.setIcon( itemToSelect.getIcon() );
	};

	MentorTools.prototype.onMentorBackTimestampChanged = function ( backtimestamp ) {
		if ( backtimestamp === null ) {
			this.$mentorAwayMessage.text( '' );
		} else {
			this.$mentorAwayMessage.text( mw.msg(
				'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-away-message',
				backtimestamp.human
			) );
		}
	};

	function initMentorTools( $body ) {
		return new MentorTools( $body );
	}

	module.exports = initMentorTools( $( '.growthexperiments-mentor-dashboard-module-mentor-tools .growthexperiments-mentor-dashboard-module-body' ) );

}() );
