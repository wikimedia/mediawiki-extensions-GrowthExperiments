<template>
	<div>
		<new-impact-summary v-if="data && !error" :data="data"></new-impact-summary>
	</div>
</template>

<script>
const NewImpactSummary = require( '../components/NewImpactSummary.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		NewImpactSummary
	},
	props: {
		data: {
			type: Object,
			required: true
		},
		error: {
			type: Object,
			default: null
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-new-impact-app-root--mobile {
	width: 100%;
}
</style>
