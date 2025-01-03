( function () {
	'use strict';

	// Keep in sync with MentorStatusManager::MAX_BACK_IN_DAYS
	const MAX_DAYS_AWAY = 365;

	function AwaySettingsDialog( config ) {
		AwaySettingsDialog.super.call( this, config );
	}
	OO.inheritClass( AwaySettingsDialog, OO.ui.ProcessDialog );

	AwaySettingsDialog.static.name = 'awaySettingsDialog';
	AwaySettingsDialog.static.title = mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-headline' );
	AwaySettingsDialog.static.actions = [
		{
			action: 'save',
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-submit' ),
			flags: [ 'primary', 'progressive' ]
		},
		{
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-cancel' ),
			flags: 'safe'
		}
	];

	AwaySettingsDialog.prototype.initialize = function () {
		AwaySettingsDialog.super.prototype.initialize.apply( this, arguments );

		this.awayForDays = new OO.ui.NumberInputWidget( {
			showButtons: false,
			min: 0,
			max: MAX_DAYS_AWAY,
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-away-for-label' ),
			step: 1,
			required: true
		} );
		this.awayForDays.connect( this, {
			change: [ 'onAwayForDaysChanged' ]
		} );

		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		this.content.$element.append(
			$( '<p>' ).text( mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-text' ) ),
			$( '<h3>' ).text( mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-away-for' ) ),
			this.awayForDays.$element
		);
		this.$body.append( this.content.$element );
	};

	AwaySettingsDialog.prototype.getSetupProcess = function ( data ) {
		const dialog = this;
		return AwaySettingsDialog.super.prototype.getSetupProcess.call( this, data )
			.next( () => {
				dialog.getActions().setAbilities( {
					save: false
				} );
			} );
	};

	AwaySettingsDialog.prototype.onAwayForDaysChanged = function () {
		const awayDaysNum = parseInt( this.awayForDays.getValue() );
		this.getActions().setAbilities( {
			save: !isNaN( awayDaysNum )
		} );
	};

	AwaySettingsDialog.prototype.getActionProcess = function ( action ) {
		const dialog = this;
		if ( action === '' ) {
			this.emit( 'cancel' );
		} else if ( action === 'save' ) {
			return new OO.ui.Process( () => {
				const awayForDays = Number( dialog.awayForDays.getValue() );
				if ( awayForDays > MAX_DAYS_AWAY ) {
					mw.notify(
						mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-away-dialog-error-toohigh', MAX_DAYS_AWAY ),
						{ type: 'error' }
					);
					return;
				}

				const backAtTimestamp = new Date();
				backAtTimestamp.setDate( backAtTimestamp.getDate() + awayForDays );

				return new mw.Api().postWithToken( 'csrf', {
					action: 'growthmanagementorlist',
					geaction: 'change',
					isaway: true,
					awaytimestamp: backAtTimestamp.toISOString()
				} ).then( ( data ) => {
					mw.notify(
						mw.msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-changed-to-away' ),
						{ type: 'info' }
					);
					dialog.emit( 'awayset', data.growthmanagementorlist.mentor.awayTimestampHuman );
					dialog.close( { action: action } );
				} ).catch( ( errorCode, response ) => {
					mw.notify(
						response.error.info,
						{ type: 'error' }
					);
				} );
			} );
		}
		return AwaySettingsDialog.super.prototype.getActionProcess.call( this, action );
	};

	module.exports = AwaySettingsDialog;
}() );
