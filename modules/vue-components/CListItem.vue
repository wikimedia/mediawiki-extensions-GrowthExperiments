<template>
	<li
		class="ext-growthExperiments-CListItem"
		:class="{
			'ext-growthExperiments-CListItem--background-color-base': backgroundColor === 'base',
			'ext-growthExperiments-CListItem--background-color-interactive-subtle': backgroundColor === 'framed'
		}">
		<slot></slot>
	</li>
</template>

<script>
// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	props: {
		backgroundColor: {
			type: String,
			default: 'base',
		},
	},
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-CListItem {
	// Overwrite MW default margin bottom for li
	margin-bottom: 0;

	&--background-color-base {
		background-color: @background-color-base;
	}

	&--background-color-interactive-subtle {
		background-color: @background-color-interactive-subtle;
	}
}
</style>
