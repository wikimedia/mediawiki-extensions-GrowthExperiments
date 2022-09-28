<template>
	<a
		class="ext-growthExperiments-CLink"
		:class="{
			'ext-growthExperiments-CLink--disabled-visited': disableVisited
		}"
		:href="href"
	>
		<slot></slot>
	</a>
</template>

<script>
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		href: { type: String, required: true },
		disableVisited: { type: Boolean, default: false }
	},
	data() {
		return {
		};
	}
};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-CLink {
	color: @color-progressive;

	&--disabled-visited {
		&:visited {
			color: @color-progressive;
		}
	}
}
</style>
