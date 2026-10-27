const { createMwApp } = require( 'vue' );
const {
	createHomeModuleInstrumentation,
	useHomeInstrument,
} = require( '../ext.growthExperiments.Home/HomeInstrumentation.js' );

// ext.personalDashboard.common is registered only when PersonalDashboard is
// installed, which is the only time this module loads. It is not a hard
// ResourceLoader dependency, so a wiki without PersonalDashboard still has a
// valid module graph.
const config = mw.config.get( 'wgGrowthExperimentsMentorship' );

if ( config ) {
	const instrumentation = createHomeModuleInstrumentation(
		'ext.growthExperiments.mentorship',
		'module_mentorship',
	);
	// eslint-disable-next-line camelcase
	const instrumentationContext = { is_mentored: !config.optedOut };

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
		} ).provide( 'HOME_MODULE_INSTRUMENTATION', instrumentation ).mount( mount );
		instrumentation.sendModuleReady( instrumentationContext );
	} );

	instrumentation.recordModuleShownAfterVisibleOnce( instrumentationContext );
	useHomeInstrument( instrumentation );
}
