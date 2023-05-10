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
				@mounted="newImpactMounted"
			></component>
		</layout>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const { DEFAULT_STREAK_TIME_FRAME } = require( './constants.js' );
const useUserImpact = require( './composables/useUserImpact.js' );
const Layout = require( './components/LayoutWrapper.vue' );
// TODO wrap NewImpact, NoEditsDisplay, ScoreCards...
// components in async components so we only load one at a time.
const NewImpact = require( './components/NewImpact.vue' );
const ErrorDisplay = require( './components/ErrorDisplay.vue' );
const ErrorDisplaySummary = require( './components/ErrorDisplaySummary.vue' );
const NewImpactSummary = require( './components/NewImpactSummary.vue' );
const NoEditsDisplay = require( './components/NoEditsDisplay.vue' );
const startTime = mw.now();

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		NewImpact,
		NewImpactSummary,
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
		const renderInThirdPerson = inject( 'RENDER_IN_THIRD_PERSON' );
		const impactComponent = renderMode === 'overlay-summary' ? 'NewImpactSummary' : 'NewImpact';
		const errorComponent = renderMode === 'overlay-summary' ? 'ErrorDisplaySummary' : 'ErrorDisplay';

		const impactData = useUserImpact( DEFAULT_STREAK_TIME_FRAME, initialUserImpactData );

		// If the module is activated, and the user hasn't already seen it, then show the
		// new impact discovery tour.
		if ( !isModuleUnactivated &&
			!mw.user.options.get( 'growthexperiments-tour-newimpact-discovery' ) &&
			renderMode === 'desktop' &&
			!renderInThirdPerson
		) {
			mw.loader.load( 'ext.guidedTour.tour.newimpact_discovery' );
		}

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
		newImpactMounted: function () {
			mw.track( 'timing.growthExperiments.newImpact.' + inject( 'RENDER_MODE' ) + '.mounted', mw.now() - startTime );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
// Hack to render the background in gray for the unactivated state after data is
// fecthed and the skeleton disappears
/* stylelint-disable-next-line selector-class-pattern */
.growthexperiments-homepage-module-new-impact-unactivated {
	&-mobile-overlay,
	&-mobile-summary {
		background-color: @background-color-interactive-subtle;
	}
}
</style>
