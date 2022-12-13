<template>
	<div class="ext-growthExperiments-App--UserImpact">
		<layout :render-mode="renderMode">
			<no-edits-display
				v-if="isModuleUnactivated"
				:is-disabled="!isSuggestedEditsEnabled"
				:is-activated="isSuggestedEditsActivated"
				:user-name="userName"
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
		const userId = inject( 'RELEVANT_USER_ID' );
		const userName = inject( 'RELEVANT_USER_USERNAME' );
		const isModuleUnactivated = inject( 'RELEVANT_USER_MODULE_UNACTIVATED' );
		const isSuggestedEditsEnabled = inject( 'RELEVANT_USER_SUGGESTED_EDITS_ENABLED' );
		const isSuggestedEditsActivated = inject( 'RELEVANT_USER_SUGGESTED_EDITS_ACTIVATED' );
		const impactComponent = renderMode === 'overlay-summary' ? 'NewImpactSummary' : 'NewImpact';
		const errorComponent = renderMode === 'overlay-summary' ? 'ErrorDisplaySummary' : 'ErrorDisplay';

		const result = useUserImpact( userId, DEFAULT_STREAK_TIME_FRAME );

		// If the module is activated, and the user hasn't already seen it, then show the
		// new impact discovery tour.
		if ( !isModuleUnactivated &&
			!mw.user.options.get( 'growthexperiments-tour-newimpact-discovery' ) &&
			renderMode === 'desktop' ) {
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
			data: result.data,
			// TODO: how to give user error feedback?

			error: result.error
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
@import '../vue-components/variables.less';
// REVIEW these styles could go on a less file and be
// loaded earlier than if hosted inside a Vue component.

// The class name needs to remain as it is to match
// the unactivated class added in the old (Impact.php) and
// new (NewImpact.php). Once the old module is removed the class
// can be renamed to match the selector pattern.
/* stylelint-disable-next-line selector-class-pattern */
.growthexperiments-homepage-module-impact-unactivated {
	&-desktop,
	&-mobile-overlay,
	&-mobile-summary {
		background-color: @background-color-framed;
	}

	&-mobile-overlay {
		&:before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			bottom: 0;
			right: 0;
			background-color: @background-color-framed;
			z-index: -1;
		}
	}
}

</style>
