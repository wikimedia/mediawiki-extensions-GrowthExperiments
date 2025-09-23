'use strict';

const InviteToSuggestedEditsDrawer = require( './InviteToSuggestedEditsDrawer.js' ),
	HelpPanelLogger = require( '../utils/HelpPanelLogger.js' );
let drawerPromise;

/**
 * Display the invite to suggested edits.
 *
 * @return {{openPromise:jQuery.Promise}}
 */
function displayDrawer() {
	let suppressClose = false;

	const drawer = new InviteToSuggestedEditsDrawer(
		new HelpPanelLogger( {
			context: 'postedit-nonsuggested',
		} ),
	);
	const closeDrawer = function () {
		if ( !suppressClose ) {
			drawer.close();
		}
	};
	$( document.body ).append( drawer.$element );
	drawer.open();

	drawer.opened.then( () => {
		// Hide the drawer if the user opens the editor again.
		// HACK ignore memorized previous ve.activationComplete events.
		suppressClose = true;
		mw.hook( 've.activationComplete' ).add( closeDrawer );
		suppressClose = false;
		drawer.logImpression();
	} );

	drawer.closed.then( () => {
		mw.hook( 've.activationComplete' ).remove( closeDrawer );
		drawer.logClose();
	} );

	return {
		openPromise: drawer.opened,
	};
}

/**
 * Display the drawer in VE (after a non-reload save) once.
 *
 * There are two error scenarios to avoid:
 * - The user makes an edit that does not trigger the invitation logic, then another one
 *   that does. mw.hook fire() calls are memorized and replayed when a handler is added,
 *   so we need to prevent showing two drawers.
 * - The user makes an edit that triggers the invitation logic, then another one that does
 *   not. We need to avoid showing the drawer again on the second edit.
 *
 * The solution for the second issue is somewhat inaccurate as it will prevent the drawer
 * from showing even if the user hits multiple thresholds on the same page, but that
 * doesn't seem big enough of a problem to warrant listening to the VE API response for a
 * more accurate signal.
 *
 * @return {{openPromise:jQuery.Promise}}
 */
function displayDrawerOnce() {
	if ( !drawerPromise ) {
		drawerPromise = displayDrawer();
	}
	return drawerPromise;
}

if ( mw.config.get( 'wgGELevelingUpInviteToSuggestedEditsImmediate' ) ) {
	// We are right after a post-edit page reload (mobile edit, or VisualEditor page creation,
	// or VisualEditor save when the editor was loaded in a situation other than action=view).
	displayDrawer();
} else {
	// We are after a VE save (which did not result in a reload), loaded via the VE save
	// complete handler, just before it tears down the editor. Wait for teardown to complete.
	mw.hook( 've.deactivationComplete' ).add( displayDrawerOnce );
}
