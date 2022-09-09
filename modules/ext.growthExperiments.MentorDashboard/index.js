/*
 * Javascript for Special:MentorDashboard
 */
( function () {
	'use strict';

	// init mentee overview non-Vue module. Once the Vue version
	// is enabled in all wikis the if check and the require of
	// this module won't be necessary. See T300532
	if ( mw.config.get( 'wgGEMentorDashboardUseVue' ) === false ) {
		require( './MenteeOverview/MenteeOverview.js' );
	}

	// init mentor tools module
	require( './MentorTools/MentorTools.js' );
}() );
