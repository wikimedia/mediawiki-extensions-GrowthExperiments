<template>
	<section
		class="ext-growthExperiments-NewImpact"
		:class="{
			'ext-growthExperiments-NewImpact--mobile': isMobileHomepage === true
		}">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<div v-if="data" class="ext-growthExperiments-NewImpact__scores">
			<score-card
				:icon="cdxIconEdit"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-edit-count' )"
			>
				<c-text size="md" weight="bold">
					<a :href="contributionsUrl" class="ext-growthExperiments-NewImpact__scores__link">
						{{ $filters.convertNumber( data.totalEditsCount ) }}
					</a>
				</c-text>
			</score-card>
			<score-card
				:icon="cdxIconHeart"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' )"
			>
				<c-text
					as="span"
					size="md"
					weight="bold">
					{{ $filters.convertNumber( data.receivedThanksCount ) }}
				</c-text>
				<template #label-info>
					<c-info-box
						:icon="cdxIconInfo"
						:close-icon="cdxIconClose"
					>
						<div class="ext-growthExperiments-NewImpact__scorecard__info">
							<span>
								<cdx-icon
									class="ext-growthExperiments-NewImpact__scorecard__info__icon"
									:icon="cdxIconInfoFilled"
								></cdx-icon>
								{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ) }}
							</span>
							<p>
								{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-info-text', userName ) }}
							</p>
						</div>
					</c-info-box>
				</template>
			</score-card>
			<score-card
				:icon="cdxIconClock"
				:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-last-edit-text' )"
			>
				<c-text
					as="span"
					size="md"
					weight="bold">
					{{ lastEditFormattedTimeAgo }}
				</c-text>
			</score-card>
			<score-card
				:icon="cdxIconChart"
				:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' )"
			>
				<c-text
					as="span"
					size="md"
					weight="bold">
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-streak-count-text', bestStreakDaysLocalisedCount ) }}
				</c-text>
				<template #label-info>
					<c-info-box
						:icon="cdxIconInfo"
						:close-icon="cdxIconClose"
					>
						<div class="ext-growthExperiments-NewImpact__scorecard__info">
							<span>
								<cdx-icon
									class="ext-growthExperiments-NewImpact__scorecard__info__icon"
									:icon="cdxIconInfoFilled"
								></cdx-icon>
								{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ) }}
							</span>
							<p>
								{{ $i18n( 'growthexperiments-homepage-impact-scores-best-streak-info-text', userName ) }}
							</p>
							<p>
								{{
									$i18n(
										'growthexperiments-homepage-impact-scores-best-streak-info-data-text',
										userName,
										$filters.convertNumber( data.longestEditingStreak.datePeriod.days ),
										bestStreakFormattedDates
									)
								}}
							</p>
						</div>
					</c-info-box>
				</template>
			</score-card>
		</div>
		<div v-if="data">
			<c-text
				class="ext-growthExperiments-NewImpact__recent-activity-title"
				as="h5"
				size="md"
				weight="bold"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-title', userName, DEFAULT_STREAK_TIME_FRAME ) }}
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
				:count-text="$filters.convertNumber( data.dailyTotalViews.count )"
				:count-label="$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label', userName )"
				:chart-title="$i18n( 'growthexperiments-homepage-impact-edited-articles-trend-chart-title' )"
				:data="data.dailyTotalViews.entries"
			></trend-chart>
		</div>
		<div v-if="data">
			<c-text
				as="h5"
				size="md"
				weight="bold"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-subheader-text', userName ) }}
			</c-text>
			<articles-list class="ext-growthExperiments-NewImpact__articles-list" :items="data.articles"></articles-list>
			<c-text weight="bold">
				<a :href="contributionsUrl">
					{{ $i18n( 'growthexperiments-homepage-impact-contributions-link', data.totalEditsCount, userName ) }}
				</a>
			</c-text>
		</div>
	</section>
</template>

<script>
const moment = require( 'moment' );
const { CdxIcon } = require( '@wikimedia/codex' );
const CInfoBox = require( '../../vue-components/CInfoBox.vue' );
const CText = require( '../../vue-components/CText.vue' );
const useUserImpact = require( '../composables/useUserImpact.js' );
const ScoreCard = require( './ScoreCard.vue' );
const RecentActivity = require( './RecentActivity.vue' );
const TrendChart = require( './TrendChart.vue' );
const ArticlesList = require( './ArticlesList.vue' );
const {
	cdxIconEdit,
	cdxIconHeart,
	cdxIconClock,
	cdxIconChart,
	cdxIconClose,
	cdxIconInfo,
	cdxIconInfoFilled
} = require( '../../vue-components/icons.json' );
// The number of columns to show in the streak graphic. Columns
// will be represented as days.
const DEFAULT_STREAK_TIME_FRAME = 60;

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		CInfoBox,
		CText,
		ArticlesList,
		RecentActivity,
		ScoreCard,
		TrendChart
	},
	props: {},
	setup() {
		const isMobileHomepage = mw.config.get( 'homepagemobile' );
		const userId = mw.config.get( 'GENewImpactRelevantUserId' );
		const { data, error } = useUserImpact( userId, DEFAULT_STREAK_TIME_FRAME );
		return {
			DEFAULT_STREAK_TIME_FRAME,
			cdxIconEdit,
			cdxIconHeart,
			cdxIconClock,
			cdxIconChart,
			isMobileHomepage,
			cdxIconClose,
			cdxIconInfo,
			cdxIconInfoFilled,
			data,
			// TODO: how to give user error feedback?
			// eslint-disable-next-line vue/no-unused-properties
			error
		};
	},
	computed: {
		contributionsUrl() {
			return mw.util.getUrl( `Special:Contributions/${this.userName}` );
		},
		lastEditMoment() {
			return moment( this.data.lastEditTimestamp * 1000 );
		},
		lastEditFormattedTimeAgo() {
			return this.lastEditMoment.fromNow();
		},
		bestStreakDaysLocalisedCount() {
			return this.$filters.convertNumber( this.data.longestEditingStreak.datePeriod.days );
		},
		bestStreakFormattedDates() {
			// FIXME: date formats are not localised
			const formatDate = ( date, format = 'MMM D YYYY' ) => {
				return moment( date ).format( format );
			};
			const today = new Date();
			let { start, end } = this.data.longestEditingStreak.datePeriod;
			start = new Date( start );
			end = new Date( end );

			// The streak start and ends on the current year and same month,
			if (
				start.getYear() === today.getYear() &&
				start.getYear() === end.getYear() &&
				start.getMonth() === end.getMonth()
			) {
				return `${formatDate( start, 'MMM D' )} — ${formatDate( end, 'D' )}`;
			}

			// REVIEW: the streak start on prior year but ends on current year is
			// not handled. ie: Aug 3 2002 - Sep 17 <current year>
			return `${formatDate( start )} — ${formatDate( end )}`;
		},
		userName() {
			return mw.config.get( 'GENewImpactRelevantUserName' );
		}
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-NewImpact {
	&--mobile {
		// Expand all content over homepage modules padding
		margin: -16px;
	}

	&__recent-activity-title {
		// Use same margin from desktop vector
		margin-top: 0.3em;
	}

	&__scores {
		display: grid;
		grid-template-columns: 1fr 1fr;
		grid-gap: 2px;
		// Expand scores stripe over homepage modules padding
		margin: 0 -16px;

		&__link {
			.disabled-visited();
		}
	}

	&__articles-list {
		padding: @padding-horizontal-base 0;
	}

	&__scorecard__info {
		width: 280px;
		margin-top: 0.5em;

		&__icon {
			margin-right: 0.5em;
		}
	}
}
</style>
