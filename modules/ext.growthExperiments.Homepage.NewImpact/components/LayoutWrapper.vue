<template>
	<!--
		The following classes are generated here:
		* ext-growthExperiments-Layout--desktop
		* ext-growthExperiments-Layout--mobile-details
		* ext-growthExperiments-Layout--mobile-overlay
		* ext-growthExperiments-Layout--mobile-summary
	-->
	<div :class="`ext-growthExperiments-Layout--${renderMode}`">
		<slot></slot>
	</div>
</template>

<script>
/**
 * Component to hold different top level styles needed for
 * each render mode the application can be rendered in.
 * See render modes in IDashboardModule.php.
 */

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	props: {
		/**
		 * The render mode to use for displaying the app. Can be
		 * one of 'desktop', 'overlay', 'overlay-summary'.
		 */
		renderMode: {
			type: String,
			default: 'desktop'
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-Layout {
	&--mobile-overlay {
		// Expand all content over homepage modules padding
		margin: -16px;
	}
}
</style>
