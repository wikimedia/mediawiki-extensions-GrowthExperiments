<template>
	<li
		class="ext-growthExperiments-CListItem"
		:class="{
			'ext-growthExperiments-CListItem--background-color-base': backgroundColor === 'base',
			'ext-growthExperiments-CListItem--background-color-framed': backgroundColor === 'framed'
		}">
		<slot></slot>
	</li>
</template>

<script>
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	props: {
		backgroundColor: {
			type: String,
			default: 'base'
		}
	}
};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-CListItem {
	// Overwrite MW default margin bottom for li
	margin-bottom: 0;

	&--background-color-base {
		background-color: @background-color-base;
	}

	&--background-color-framed {
		background-color: @background-color-framed;
	}
}
</style>
