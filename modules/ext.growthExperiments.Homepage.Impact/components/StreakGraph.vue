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
			<c-text size="sm" color="placeholder">
				{{ startLabel }}
			</c-text>
			<c-text size="sm" color="placeholder">
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
	compilerOptions: { whitespace: 'condense' },
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
		getColumnTitleFn: {
			type: Function,
			default: ( x ) => x
		},
		getColumnValueFn: {
			type: Function,
			required: true
		},
		backgroundColor: {
			type: String,
			// @colorGray200
			default: '#eaecf0'
		},
		fillColor: {
			type: String,
			// @colorBlue600
			default: '#36c'
		}
	},
	setup() {
		return {};
	},
	computed: {
		graphStyles() {
			const minWidth = Math.floor( 160 / this.columns );
			return {
				'grid-template-columns': `repeat(${ this.columns }, minmax(${ minWidth }px, 1fr))`
			};
		}
	},
	methods: {
		getColumnStyle( index ) {
			const contribPercentage = this.getColumnValueFn( index );
			return {
				// eslint-disable-next-line vue/max-len
				'background-image': `linear-gradient(0deg, ${ this.fillColor } ${ contribPercentage }%, ${ this.backgroundColor } 0)`
			};
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-StreakGraph {
	display: flex;
	flex-direction: column;
	// Columns and labels will display LTR even in RTL languages (older date left, recent date right)
	direction: ltr;

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
