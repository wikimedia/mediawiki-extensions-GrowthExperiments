const { createMwApp } = require( 'vue' );

// ext.personalDashboard.common is registered only when PersonalDashboard is
// installed, which is the only time this module loads. It is not a hard
// ResourceLoader dependency, so a wiki without PersonalDashboard still has a
// valid module graph.
const config = mw.config.get( 'wgGrowthExperimentsMentorship' );

if ( config ) {
	mw.loader.using( 'ext.personalDashboard.common' ).then( () => {
		const App = require( './App.vue' );
		const mount = document.createElement( 'div' );
		document.body.appendChild( mount );
		createMwApp( App, {
			headerSlot: config.headerSlot,
			optedOut: config.optedOut,
			preintroDismissedPref: config.preintroDismissedPref,
			poster: mw.config.get( 'wgGrowthExperimentsMentorshipPoster' ),
			mentorGender: mw.config.get( 'wgGrowthExperimentsMentorshipMentorGender', 'unknown' ),
		} ).mount( mount );
	} );
}
