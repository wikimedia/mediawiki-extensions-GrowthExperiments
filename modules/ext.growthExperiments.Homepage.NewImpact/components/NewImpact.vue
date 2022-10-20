<template>
	<section class="ext-growthExperiments-NewImpact">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<div v-if="data" class="ext-growthExperiments-NewImpact__scores">
			<score-card
				:icon="cdxIconEdit"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-edit-count' )"
			>
				<c-link :href="contributionsUrl" :disable-visited="true">
					<c-text size="md" weight="bold">
						{{ $filters.convertNumber( data.totalEditsCount ) }}
					</c-text>
				</c-link>
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
			</score-card>
		</div>
		<div v-if="data">
			<c-text
				as="h5"
				size="md"
				weight="bold"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-title', userName, DEFAULT_STREAK_TIME_FRAME ) }}
			</c-text>
			<recent-activity
				:contribs="data.contributions"
				:time-frame="DEFAULT_STREAK_TIME_FRAME"
				:date-format="DEFAULT_STREAK_DISPLAY_DATE_FORMAT"
			></recent-activity>
		</div>
	</section>
</template>

<script>
const moment = require( 'moment' );
const ScoreCard = require( './ScoreCard.vue' );
const RecentActivity = require( './RecentActivity.vue' );
const CText = require( '../../vue-components/CText.vue' );
const CLink = require( '../../vue-components/CLink.vue' );
const useUserImpact = require( '../composables/useUserImpact.js' );
const { cdxIconEdit, cdxIconHeart, cdxIconClock, cdxIconChart } = require( '../../vue-components/icons.json' );
// REVIEW: the proposed format in designs "Feb 3" is not localised across languages
const DEFAULT_STREAK_DISPLAY_DATE_FORMAT = 'MMM D';
// The number of columns to show in the streak graphic. Columns
// will be represented as days.
const DEFAULT_STREAK_TIME_FRAME = 60;

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		RecentActivity,
		ScoreCard,
		CText,
		CLink
	},
	props: {},
	setup() {
		const userId = mw.config.get( 'GENewImpactRelevantUserId' );
		const { data, error } = useUserImpact( userId, DEFAULT_STREAK_TIME_FRAME );
		return {
			DEFAULT_STREAK_TIME_FRAME,
			DEFAULT_STREAK_DISPLAY_DATE_FORMAT,
			cdxIconEdit,
			cdxIconHeart,
			cdxIconClock,
			cdxIconChart,
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
			return this.$filters.convertNumber( this.data.bestStreak.datePeriod.days );
		},
		userName() {
			return mw.config.get( 'GENewImpactRelevantUserName' );
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-NewImpact {
	&__scores {
		display: grid;
		grid-template-columns: 1fr 1fr;
		grid-gap: 2px;
		// Expand scores stripe over homepage modules padding
		margin: 0 -16px;
	}
}
</style>
