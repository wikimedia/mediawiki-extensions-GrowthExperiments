<template>
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
@import '../../vue-components/variables.less';
// ext-growthExperiments-new-impact-app-root--mobile class is
// only added when mounting the app for the mobile overlay. Given all components
// rendered inside are responsive, set width to stretch over available horizontal space
/* stylelint-disable-next-line selector-class-pattern */
.ext-growthExperiments-new-impact-app-root--mobile {
	width: 100%;
}

.ext-growthExperiments-Layout {
	&--overlay {
		// Expand all content over homepage modules padding
		margin: -16px;
	}
}
</style>
