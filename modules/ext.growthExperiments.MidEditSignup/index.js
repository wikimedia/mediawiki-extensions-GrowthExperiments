'use strict';

/**
 * This module does two things:
 * #1: sets a sessionStorage flag when the user signs up in the middle of an edit;
 * #2: based on that, shows a dialog about the welcome survey after the user finishes the edit.
 */
( function () {
	function showMidEditSignupDialog() {
		const HelpPanelLogger = require( '../utils/HelpPanelLogger.js' ),
			helpPanelLogger = new HelpPanelLogger();

		mw.storage.session.remove( 'ge.midEditSignup' );
		mw.cookie.set( 'ge.midEditSignup', null );
		mw.loader.using( [ 'mediawiki.user', 'mediawiki.Title', 'oojs-ui-windows' ] ).then( () => {
			const MessageDialogWithVerticalButtons = require( '../ui-components/MessageDialogWithVerticalButtons.js' ),
				messageDialog = new MessageDialogWithVerticalButtons(),
				windowManager = new OO.ui.WindowManager();

			$( document.body ).append( windowManager.$element );
			windowManager.addWindows( [ messageDialog ] );
			const lifecycle = windowManager.openWindow( messageDialog, {
				title: mw.message( 'welcomesurvey-mideditsignup-title' )
					.params( [ mw.user.getName() ] )
					.text(),
				message: mw.message( 'welcomesurvey-mideditsignup-body' ).text(),
				actions: [
					{ action: 'homepage', label: OO.ui.deferMsg( 'welcomesurvey-mideditsignup-button-homepage' ) },
					{ action: 'close', label: OO.ui.deferMsg( 'welcomesurvey-mideditsignup-button-close' ) },
				],
			} );
			lifecycle.opened.then( () => {
				helpPanelLogger.log( 'postsignup-impression' );
			} );
			lifecycle.closing.then( ( data ) => {
				if ( data.action === 'homepage' ) {
					helpPanelLogger.log( 'postsignup-homepage' );
					location.href = new mw.Title( 'Special:Homepage' ).getUrl();
				} else {
					helpPanelLogger.log( 'postsignup-close' );
				}
			} );
		} );
	}

	if ( mw.config.get( 'wgGEMidEditSignup' ) ) {
		// We are at step #1.
		mw.storage.session.set( 'ge.midEditSignup', true );
		mw.cookie.set( 'ge.midEditSignup', 1 );
	} else {
		// We are at step #2, maybe. Postedit detection is cookie-based and thus unreliable.
		// The hook won't run on false positives so that's fine.
		mw.hook( 'postEdit' ).add( showMidEditSignupDialog );
		mw.hook( 'postEditMobile' ).add( showMidEditSignupDialog );
	}

}() );
