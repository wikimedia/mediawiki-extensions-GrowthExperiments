<template>
	<div class="ext-growthExperiments-Layout--overlay">
		<new-impact v-if="data && !error" :data="data"></new-impact>
	</div>
</template>

<script>
const NewImpact = require( '../components/NewImpact.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		NewImpact
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
.ext-growthExperiments-Layout--overlay {
	// Expand all content over homepage modules padding
	margin: -16px;
}
</style>
