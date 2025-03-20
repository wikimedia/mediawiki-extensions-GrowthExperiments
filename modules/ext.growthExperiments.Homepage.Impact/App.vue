<template>
	<div class="ext-growthExperiments-App--UserImpact">
		<layout :render-mode="renderMode">
			<no-edits-display
				v-if="isModuleUnactivated"
				:is-disabled="!isSuggestedEditsEnabled"
				:is-activated="isSuggestedEditsActivated"
				:user-name="userName"
				:data="data"
			></no-edits-display>
			<component
				:is="errorComponent"
				v-else-if="error"
			></component>
			<component
				:is="impactComponent"
				v-else-if="data && !error"
				:user-name="userName"
				:data="data"
				@mounted="impactMounted"
			></component>
		</layout>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const { DEFAULT_STREAK_TIME_FRAME } = require( './constants.js' );
const { useUserImpact } = require( './composables/useUserImpact.js' );
const Layout = require( './components/LayoutWrapper.vue' );
// TODO wrap Impact, NoEditsDisplay, ScoreCards...
// components in async components so we only load one at a time.
const Impact = require( './components/Impact.vue' );
const ErrorDisplay = require( './components/ErrorDisplay.vue' );
const ErrorDisplaySummary = require( './components/ErrorDisplaySummary.vue' );
const ImpactSummary = require( './components/ImpactSummary.vue' );
const NoEditsDisplay = require( './components/NoEditsDisplay.vue' );
const startTime = mw.now();

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		Impact,
		ImpactSummary,
		NoEditsDisplay,
		ErrorDisplay,
		ErrorDisplaySummary,
		Layout
	},
	setup() {
		const renderMode = inject( 'RENDER_MODE' );
		const userName = inject( 'RELEVANT_USER_USERNAME' );
		const initialUserImpactData = inject( 'RELEVANT_USER_DATA' );
		const fetchError = inject( 'FETCH_ERROR' );
		const isModuleUnactivated = inject( 'RELEVANT_USER_MODULE_UNACTIVATED' );
		const isSuggestedEditsEnabled = inject( 'RELEVANT_USER_SUGGESTED_EDITS_ENABLED' );
		const isSuggestedEditsActivated = inject( 'RELEVANT_USER_SUGGESTED_EDITS_ACTIVATED' );
		const impactComponent = renderMode === 'mobile-summary' ? 'ImpactSummary' : 'Impact';
		const errorComponent = renderMode === 'mobile-summary' ? 'ErrorDisplaySummary' : 'ErrorDisplay';

		const impactData = useUserImpact( DEFAULT_STREAK_TIME_FRAME, initialUserImpactData );

		return {
			renderMode,
			userName,
			isSuggestedEditsEnabled,
			isSuggestedEditsActivated,
			isModuleUnactivated,
			impactComponent,
			errorComponent,
			data: impactData,
			// TODO: how to give user error feedback?
			error: fetchError
		};
	},
	methods: {
		impactMounted: function () {
			const duration = mw.now() - startTime;
			mw.track( 'timing.growthExperiments.newImpact.' + inject( 'RENDER_MODE' ) + '.mounted', duration );
			mw.track(
				'stats.mediawiki_GrowthExperiments_homepage_impact_mounted_seconds',
				duration,
				{
					// eslint-disable-next-line camelcase
					render_mode: inject( 'RENDER_MODE' ).replace( '-', '_' ),
					wiki: mw.config.get( 'wgDBname' )
				}
			);
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
// Hack to render the background in gray for the unactivated state after data is
// fecthed and the skeleton disappears
/* stylelint-disable-next-line selector-class-pattern */
.growthexperiments-homepage-module-impact-unactivated {
	&-mobile-overlay,
	&-mobile-summary {
		background-color: @background-color-interactive-subtle;
	}
}
</style>
