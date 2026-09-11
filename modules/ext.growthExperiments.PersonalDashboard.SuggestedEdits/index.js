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
const { defineAsyncComponent } = require( 'vue' );

module.exports = defineAsyncComponent(
	() => mw.loader.using( 'ext.personalDashboard.common' )
		.then( () => require( './App.vue' ) ),
);
