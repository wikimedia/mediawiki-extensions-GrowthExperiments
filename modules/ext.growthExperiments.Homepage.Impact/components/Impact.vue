<template>
	<section
		class="ext-growthExperiments-NewImpact"
		:class="{
			'ext-growthExperiments-NewImpact--mobile': isMobileHomepage === true
		}">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<div v-if="data">
			<c-score-cards
				:user-name="userName"
				:render-third-person="renderThirdPerson"
				:has-intl="hasIntl"
				:data="data"
				@interaction="$log( 'impact', $event )"
			></c-score-cards>
		</div>
		<div v-if="data && hasIntl">
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
			></recent-activity>
		</div>
		<div v-if="data && data.articlesViewsCount > 0">
			<trend-chart
				id="impact"
				:count-label="countLabelText"
				:chart-title="chartTitle"
				:pageview-total="data.articlesViewsCount"
				:data="data.dailyTotalViews"
			></trend-chart>
		</div>
		<div v-if="data && data.articles.length > 0">
			<c-text
				class="ext-growthExperiments-NewImpact__section-title ext-growthExperiments-increaseSpecificity"
				as="h3"
				size="md"
				weight="bold"
			>
				{{ articlesListTitleText }}
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
const { inject } = require( 'vue' );
const { DEFAULT_STREAK_TIME_FRAME } = require( '../constants.js' );
const CText = require( '../../vue-components/CText.vue' );
const CScoreCards = require( '../../vue-components/CScoreCards.vue' );

const RecentActivity = require( './RecentActivity.vue' );
const TrendChart = require( './TrendChart.vue' );
const ArticlesList = require( './ArticlesList.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	components: {
		CText,
		CScoreCards,
		ArticlesList,
		RecentActivity,
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
		const renderThirdPerson = inject( 'RENDER_IN_THIRD_PERSON' );
		const hasIntl = inject( 'BROWSER_HAS_INTL' );

		return {
			hasIntl,
			DEFAULT_STREAK_TIME_FRAME,
			isMobileHomepage,
			renderThirdPerson
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
			return this.renderThirdPerson ?
				this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-title-third-person',
					this.$filters.convertNumber( DEFAULT_STREAK_TIME_FRAME )
				).text() :
				this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-title',
					'', // used to be the username
					this.$filters.convertNumber( DEFAULT_STREAK_TIME_FRAME )
				).text();
		},
		contributionsLinkText() {
			return this.$i18n(
				'growthexperiments-homepage-impact-contributions-link'
			).text();
		},
		contributionsUrl() {
			return mw.util.getUrl( `Special:Contributions/${ this.userName }` );
		},
		countLabelText() {
			return this.renderThirdPerson ?
				this.$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label-third-person' ).text() :
				this.$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label' ).text();
		},
		articlesListTitleText() {
			return this.renderThirdPerson ?
				this.$i18n( 'growthexperiments-homepage-impact-subheader-text-third-person' ).text() :
				this.$i18n( 'growthexperiments-homepage-impact-subheader-text' ).text();
		}
	},
	mounted() {
		this.$emit( 'mounted' );
	}
};
</script>
