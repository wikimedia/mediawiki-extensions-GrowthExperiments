<template>
	<div class="ext-growthExperiments-TrendChart">
		<c-text
			as="span"
			size="xxl"
			weight="bold"
			class="ext-growthExperiments-TrendChart__number"
		>
			{{ countText }}
		</c-text>
		<c-text as="span">
			{{ countLabel }}
		</c-text>
		<div class="ext-growthExperiments-TrendChart__graph">
			<c-sparkline
				:title="chartTitle"
				:data="data"
				:dimensions="{ width: 448, height: 24 }"
				:x-accessor="xAccessor"
				:y-accessor="yAccessor"
			></c-sparkline>
		</div>
	</div>
</template>

<script>
const { defineAsyncComponent } = require( 'vue' );
const CText = require( '../../vue-components/CText.vue' );
const CSparkline = defineAsyncComponent( () => {
	if ( mw.config.get( 'GENewImpactD3Enabled' ) ) {
		return mw.loader.using( 'ext.growthExperiments.d3' )
			.then( () => require( '../../vue-components/CSparkline.vue' ) );
	} else {
		// Maybe fallback to a static image
		return Promise.resolve( null );
	}
} );
const xAccessor = ( d ) => d.date;
const yAccessor = ( d ) => d.views;
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CSparkline,
		CText
	},
	props: {
		chartTitle: {
			type: String,
			default: null
		},
		countText: {
			type: String,
			default: null
		},
		countLabel: {
			type: String,
			default: null
		},
		data: {
			type: Object,
			default: () => ( {} )
		}
	},
	setup() {
		return {
			xAccessor,
			yAccessor
		};
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-TrendChart {
	padding: 8px 0;

	&__number {
		margin-right: calc( @padding-horizontal-base / 2 );
	}

	&__graph {
		// Overwrite vector-body rule, 1.6em
		// causes extra vertical height to appear under the chart
		line-height: 1em;
	}
}
</style>
