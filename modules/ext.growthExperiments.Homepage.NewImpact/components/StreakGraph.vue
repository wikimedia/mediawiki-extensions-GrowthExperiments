<template>
	<div class="ext-growthExperiments-StreakGraph">
		<div class="ext-growthExperiments-StreakGraph__graph" :style="graphStyles">
			<div
				v-for="( col, index ) in columns"
				:key="col"
				:style="getColumnStyle( index )"
				:title="getColumnTitleFn( index )"
			>
			</div>
		</div>
		<div class="ext-growthExperiments-StreakGraph__legend">
			<c-text size="small" color="subtle">
				{{ startLabel }}
			</c-text>
			<c-text size="small" color="subtle">
				{{ endLabel }}
			</c-text>
		</div>
	</div>
</template>

<script>
const CText = require( '../../vue-components/CText.vue' );
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText
	},
	props: {
		startLabel: {
			type: String,
			required: true
		},
		endLabel: {
			type: String,
			required: true
		},
		columns: {
			type: Number,
			default: 30
		},
		data: {
			type: Object,
			default: () => ( {} )
		},
		getColumnTitleFn: {
			type: Function,
			default: ( x ) => x
		}
	},
	setup() {
		return {};
	},
	computed: {
		min() {
			return Math.min( ...this.data.entries );
		},
		max() {
			return Math.max( ...this.data.entries );
		},
		graphStyles() {
			const minWidth = Math.floor( 160 / this.columns );
			return {
				'grid-template-columns': `repeat(${this.columns}, minmax(${minWidth}px, 1fr))`
			};
		}
	},
	methods: {
		getColumnStyle( index ) {
			const value = this.data.entries[ index ];
			const range = this.max - this.min;
			const scale = 100 / range;
			const contribPercentage = value * scale;
			// TODO create file for "styles in javascript"
			return {
				'background-image': `linear-gradient(0deg, #36c ${contribPercentage}%, #eaecf0 0)`
			};
		}
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-StreakGraph {
	display: flex;
	flex-direction: column;

	&__graph {
		height: 24px;
		display: grid;
		gap: 2px;
	}

	&__legend {
		display: flex;
		justify-content: space-between;
	}
}
</style>
