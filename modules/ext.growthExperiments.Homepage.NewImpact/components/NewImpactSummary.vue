<template>
	<section class="ext-growthExperiments-NewImpactSummary">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<trend-chart
			v-if="data"
			id="impact-summary"
			:count-text="$filters.convertNumber( data.dailyTotalViews.count )"
			:count-label="$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label', userName )"
			:chart-title="$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-title' )"
			:data="data.dailyTotalViews.entries"
		></trend-chart>
		<div v-if="data" class="ext-growthExperiments-NewImpactSummary__info">
			<div class="ext-growthExperiments-NewImpactSummary__info__box">
				<c-text
					size="medium"
				>
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-last-edit-text', lastEditFormattedDate ) }}
				</c-text>
				<c-text
					size="medium"
					weight="bold"
				>
					{{ lastEditFormattedTimeAgo }}
				</c-text>
			</div>
			<div class="ext-growthExperiments-NewImpactSummary__info__box">
				<c-text
					size="medium"
				>
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text', bestStreakFormattedDates ) }}
				</c-text>
				<c-text
					size="medium"
					weight="bold"
				>
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-streak-count-text', bestStreakDaysLocalisedCount ) }}
				</c-text>
			</div>
		</div>
	</section>
</template>

<script>
const moment = require( 'moment' );
const TrendChart = require( './TrendChart.vue' );
const CText = require( '../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		TrendChart
	},
	props: {
		userName: {
			type: String,
			required: true
		},
		data: {
			type: Object,
			required: true
		}
	},
	computed: {
		lastEditMoment() {
			return moment( this.data.lastEditTimestamp * 1000 );
		},
		lastEditFormattedTimeAgo() {
			return this.lastEditMoment.fromNow();
		},
		bestStreakDaysLocalisedCount() {
			return this.$filters.convertNumber( this.data.longestEditingStreak.datePeriod.days );
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-NewImpactSummary {
	&__info {
		display: flex;
	}

	&__info__box {
		flex: 1;
	}
}

</style>
