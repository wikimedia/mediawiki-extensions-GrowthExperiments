<template>
	<div class="ext-growthExperiments-App--UserImpact">
		<component
			:is="layoutComponent"
			:data="data"
			:error="error"
		></component>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const { DEFAULT_STREAK_TIME_FRAME } = require( './constants.js' );
const useUserImpact = require( './composables/useUserImpact.js' );
// TODO wrap layout components in async components so we only
// load one layout per app.
const LayoutDesktop = require( './layouts/LayoutDesktop.vue' );
const LayoutOverlay = require( './layouts/LayoutOverlay.vue' );
const LayoutOverlaySummary = require( './layouts/LayoutOverlaySummary.vue' );
const LAYOUT_COMPONENTS = {
	desktop: 'Desktop',
	overlay: 'Overlay',
	'overlay-summary': 'OverlaySummary'
};

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		LayoutDesktop,
		LayoutOverlay,
		LayoutOverlaySummary
	},
	props: {
		/**
		 * The layout to use for displaying the app. Can be
		 * one of 'desktop', 'overlay', 'overlay-summary'.
		 */
		layout: {
			type: String,
			default: 'desktop'
		}
	},
	setup( props ) {
		const userId = inject( 'USER_TO_SHOW_ID' );
		const { data, error } = useUserImpact( userId, DEFAULT_STREAK_TIME_FRAME );
		const layoutComponent = `Layout${LAYOUT_COMPONENTS[ props.layout ]}`;
		return {
			data,
			layoutComponent,
			// TODO: how to give user error feedback?
			// eslint-disable-next-line vue/no-unused-properties
			error
		};
	}
};
</script>
