<template>
	<div class="ext-growthExperiments-TrendChart">
		<c-text
			as="span"
			size="xxl"
			weight="bold"
			class="ext-growthExperiments-TrendChart__number"
		>
			{{ formattedPageviewTotal }}
		</c-text>
		<c-text as="span">
			{{ countLabel }}
		</c-text>
		<div class="ext-growthExperiments-TrendChart__graph">
			<c-sparkline
				:id="`main-${id}`"
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
const { inject } = require( 'vue' );
const { getIntlLocale } = require( '../../utils/Utils.js' );
const CText = require( '../../vue-components/CText.vue' );
const CSparkline = require( '../../vue-components/CSparkline.vue' );
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
		id: {
			type: String,
			required: true
		},
		chartTitle: {
			type: String,
			required: true
		},
		countLabel: {
			type: String,
			default: null
		},
		pageviewTotal: {
			type: Number,
			default: null
		},
		data: {
			type: Array,
			default: () => ( [] )
		}
	},
	setup() {
		const hasIntl = inject( 'BROWSER_HAS_INTL' );
		return {
			hasIntl,
			xAccessor,
			yAccessor
		};
	},
	computed: {
		formattedPageviewTotal() {
			// Use abbreviated number format on mobile preview.
			if ( mw.config.get( 'homepagemobile' ) && this.hasIntl ) {
				const locale = getIntlLocale(),
					numberFormatter = new Intl.NumberFormat( locale, { notation: 'compact', maximumFractionDigits: 1 } );
				return numberFormatter.format( this.pageviewTotal );
			} else {
				return this.$filters.convertNumber( this.pageviewTotal );
			}
		}
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';

.ext-growthExperiments-TrendChart {
	padding: 8px 0;

	&__number {
		margin-right: @spacing-35;
	}

	&__graph {
		// Overwrite vector-body rule, 1.6em
		// causes extra vertical height to appear under the chart
		line-height: 1em;
	}
}
</style>
