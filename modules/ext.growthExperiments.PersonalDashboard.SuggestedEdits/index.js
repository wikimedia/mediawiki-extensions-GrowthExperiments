/*
 * The module's main package file, so PersonalDashboard's island loader gets
 * this component back from
 * require( 'ext.growthExperiments.PersonalDashboard.SuggestedEdits' ).
 *
 * ext.personalDashboard.common is only registered when PersonalDashboard is
 * installed, which is exactly when the dashboard mounts this island, so we pull
 * it in here rather than declaring a ResourceLoader dependency GrowthExperiments
 * can't satisfy on a wiki without PersonalDashboard. IslandMount already wraps
 * islands in <suspense>, so App.vue resolves in place with no loading gap of our
 * own to render.
 */
const { defineAsyncComponent, defineComponent, h, provide } = require( 'vue' );
const {
	createHomeModuleInstrumentation,
	useHomeInstrument,
} = require( '../ext.growthExperiments.Home/HomeInstrumentation.js' );
const instrumentation = createHomeModuleInstrumentation(
	'ext.growthExperiments.PersonalDashboard.SuggestedEdits',
	'module_suggested_edits',
);
useHomeInstrument( instrumentation );

module.exports = defineAsyncComponent(
	() => mw.loader.using( 'ext.personalDashboard.common' ).then( () => {
		const App = require( './App.vue' );
		return defineComponent( {
			name: 'SuggestedEditsInstrumented',
			setup() {
				provide( 'HOME_MODULE_INSTRUMENTATION', instrumentation );
				return () => h( App );
			},
		} );
	} ),
);
