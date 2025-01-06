<template>
	<section class="ext-growthExperiments-ImpactSummary">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<trend-chart
			v-if="data"
			id="impact-summary"
			:count-label="$i18n(
				'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label'
			).text()"
			:chart-title="$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-title' ).text()"
			:pageview-total="data.articlesViewsCount"
			:data="data.dailyTotalViews"
		></trend-chart>
		<info-boxes
			v-if="data"
			:data="data"
		></info-boxes>
	</section>
</template>

<script>
const TrendChart = require( './TrendChart.vue' );
const InfoBoxes = require( './InfoBoxes.vue' );

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		TrendChart,
		InfoBoxes
	},
	props: {
		data: {
			type: Object,
			required: true
		}
	},
	emits: [ 'mounted' ],
	mounted() {
		this.$emit( 'mounted' );
	}
};
</script>
