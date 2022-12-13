<template>
	<section
		class="ext-growthExperiments-NewImpact"
		:class="{
			'ext-growthExperiments-NewImpact--mobile': isMobileHomepage === true
		}">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<div v-if="data">
			<score-cards
				:user-name="userName"
				:data="data"
				:contributions-url="contributionsUrl"
				:thanks-url="thanksUrl"
			></score-cards>
		</div>
		<div v-if="data">
			<c-text
				class="ext-growthExperiments-NewImpact__section-title ext-growthExperiments-increaseSpecificity"
				as="h3"
				size="md"
				weight="bold"
			>
				{{ recentActivityTitleText }}
			</c-text>
			<recent-activity
				:is-mobile="isMobileHomepage"
				:contribs="data.contributions"
				:time-frame="DEFAULT_STREAK_TIME_FRAME"
				date-format="MMM D"
			></recent-activity>
		</div>
		<div v-if="data">
			<trend-chart
				id="impact"
				:count-label="$i18n(
					'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label', userName
				).text()"
				:chart-title="chartTitle"
				:pageview-total="data.articlesViewsCount"
				:data="data.dailyTotalViews"
			></trend-chart>
		</div>
		<div v-if="data">
			<c-text
				class="ext-growthExperiments-NewImpact__section-title ext-growthExperiments-increaseSpecificity"
				as="h3"
				size="md"
				weight="bold"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-subheader-text', userName ).text() }}
			</c-text>
			<articles-list class="ext-growthExperiments-NewImpact__articles-list" :items="data.articles"></articles-list>
			<c-text weight="bold">
				<a
					data-link-id="impact-contributions"
					:href="contributionsUrl"
					class="ext-growthExperiments-NewImpact__contributions-link"
				>
					{{ contributionsLinkText }}
				</a>
			</c-text>
		</div>
	</section>
</template>

<script>
const { DEFAULT_STREAK_TIME_FRAME } = require( '../constants.js' );
const CText = require( '../../vue-components/CText.vue' );

const ScoreCards = require( './ScoreCards.vue' );
const RecentActivity = require( './RecentActivity.vue' );
const TrendChart = require( './TrendChart.vue' );
const ArticlesList = require( './ArticlesList.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		ArticlesList,
		RecentActivity,
		ScoreCards,
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
	emits: [ 'mounted' ],
	setup() {
		// TODO The value is only used in the RecentActivity component.
		// Clarify with design if the different flex display is subject to the
		// platform or the viewport.
		const isMobileHomepage = mw.config.get( 'homepagemobile' );

		return {
			DEFAULT_STREAK_TIME_FRAME,
			isMobileHomepage
		};
	},
	computed: {
		chartTitle() {
			return this.$i18n(
				'growthexperiments-homepage-impact-edited-articles-trend-chart-title',
				this.$filters.convertNumber( DEFAULT_STREAK_TIME_FRAME )
			).text();
		},
		recentActivityTitleText() {
			return this.$i18n(
				'growthexperiments-homepage-impact-recent-activity-title',
				this.userName,
				this.$filters.convertNumber( DEFAULT_STREAK_TIME_FRAME )
			).text();
		},
		contributionsLinkText() {
			return this.$i18n(
				'growthexperiments-homepage-impact-contributions-link',
				this.$filters.convertNumber( this.data.totalEditsCount ),
				this.userName
			).text();
		},
		contributionsUrl() {
			return mw.util.getUrl( `Special:Contributions/${this.userName}` );
		},
		thanksUrl() {
			return mw.util.getUrl( 'Special:Log', {
				type: 'thanks',
				page: this.userName
			} );
		}
	},
	mounted() {
		this.$emit( 'mounted' );
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-NewImpact {
	&__section-title.ext-growthExperiments-increaseSpecificity {
		// Use same margin from desktop vector
		margin-top: 0.3em;
		font-size: @font-size-100;
	}

	&__contributions-link {
		.disabled-visited();
	}

	&__articles-list {
		padding: @padding-horizontal-base 0;
	}
}
</style>
