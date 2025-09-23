( function () {
	'use strict';

	const MentorshipOptoutDialog = require( './MentorshipOptoutDialog.js' ),
		MentorshipOptoutReasonDialog = require( './MentorshipOptoutReasonDialog.js' );

	/**
	 * @param {OO.ui.WindowManager} windowManager
	 * @param {string} mode
	 * @constructor
	 */
	function MentorshipOptoutProcess( windowManager, mode ) {
		this.windowManager = windowManager;

		this.optoutDialog = new MentorshipOptoutDialog( {} );
		this.optoutDialog.connect( this, {
			confirmation: [ 'onOptoutConfirmed' ],
		} );

		this.reasonDialog = new MentorshipOptoutReasonDialog( {
			mode: mode,
		} );

		this.windowManager.addWindows( [
			this.optoutDialog,
			this.reasonDialog,
		] );
	}

	MentorshipOptoutProcess.prototype.showOptoutDialog = function () {
		this.windowManager.openWindow( this.optoutDialog );
	};

	MentorshipOptoutProcess.prototype.onOptoutConfirmed = function () {
		const process = this;

		// HACK: For some reason, opening the window without a delay does not work as intended.
		setTimeout( () => {
			const reasonDialog = process.windowManager.openWindow( process.reasonDialog );
			reasonDialog.closed.then( () => {
				new mw.Api().postWithToken( 'csrf', {
					action: 'growthsetmenteestatus',
					state: 'optout',
				} ).then( () => {
					history.replaceState( null, '', mw.util.getUrl( 'Special:Homepage' ) );
					window.location.reload();
				} );
			} );
		}, 100 );
	};

	module.exports = MentorshipOptoutProcess;
}() );
