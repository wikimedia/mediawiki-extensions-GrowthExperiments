<template>
	<div class="ext-growthExperiments-ScoreCards">
		<score-card
			:icon="cdxIconEdit"
			:label="$i18n( 'growthexperiments-homepage-impact-scores-edit-count' ).text()"
		>
			<c-text size="md" weight="bold">
				<a :href="contributionsUrl" class="ext-growthExperiments-ScoreCards__link">
					{{ totalEditsCount }}
				</a>
			</c-text>
		</score-card>
		<score-card
			:icon="cdxIconUserTalk"
			:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
		>
			<c-text
				as="span"
				size="md"
				weight="bold">
				{{ receivedThanksCount }}
			</c-text>
			<template #label-info>
				<c-info-box
					:icon="cdxIconInfo"
					:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-label' ).text()"
					:close-icon="cdxIconClose"
				>
					<div class="ext-growthExperiments-ScoreCards__scorecard__info">
						<span>
							<cdx-icon
								class="ext-growthExperiments-ScoreCards__scorecard__info__icon"
								:icon="cdxIconInfoFilled"
							></cdx-icon>
							{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text() }}
						</span>
						<p>
							{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-info-text', userName ).text() }}
						</p>
					</div>
				</c-info-box>
			</template>
		</score-card>
		<score-card
			:icon="cdxIconClock"
			:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-last-edit-text' ).text()"
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
			:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
		>
			<c-text
				as="span"
				size="md"
				weight="bold">
				{{ longestEditingStreakCount }}
			</c-text>
			<template #label-info>
				<c-info-box
					:icon="cdxIconInfo"
					:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-streak-info-label' ).text()"
					:close-icon="cdxIconClose"
				>
					<div class="ext-growthExperiments-ScoreCards__scorecard__info">
						<span>
							<cdx-icon
								class="ext-growthExperiments-ScoreCards__scorecard__info__icon"
								:icon="cdxIconInfoFilled"
							></cdx-icon>
							{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text() }}
						</span>
						<p>
							{{ $i18n( 'growthexperiments-homepage-impact-scores-best-streak-info-text', userName ).text() }}
						</p>
						<p>
							{{
								longestEditingStreakText
							}}
						</p>
					</div>
				</c-info-box>
			</template>
		</score-card>
	</div>
</template>

<script>
const moment = require( 'moment' );
const { inject } = require( 'vue' );
const { CdxIcon } = require( '@wikimedia/codex' );
const ScoreCard = require( './ScoreCard.vue' );
const CText = require( '../../vue-components/CText.vue' );
const CInfoBox = require( '../../vue-components/CInfoBox.vue' );
const {
	cdxIconEdit,
	cdxIconUserTalk,
	cdxIconClock,
	cdxIconChart,
	cdxIconClose,
	cdxIconInfo,
	cdxIconInfoFilled
} = require( '../../vue-components/icons.json' );
const { NO_DATA_CHARACTER } = require( '../constants.js' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		ScoreCard,
		CInfoBox,
		CText,
		CdxIcon
	},
	props: {
		contributionsUrl: {
			type: String,
			default: null
		},
		data: {
			type: Object,
			default: null
		}
	},
	setup() {
		const userName = inject( 'RELEVANT_USER_USERNAME' );
		return {
			userName,
			cdxIconEdit,
			cdxIconUserTalk,
			cdxIconClock,
			cdxIconChart,
			cdxIconClose,
			cdxIconInfo,
			cdxIconInfoFilled
		};
	},
	computed: {
		totalEditsCount() {
			return this.data ?
				this.$filters.convertNumber( this.data.totalEditsCount ) :
				NO_DATA_CHARACTER;
		},
		receivedThanksCount() {
			return this.data ?
				this.$filters.convertNumber( this.data.receivedThanksCount ) :
				NO_DATA_CHARACTER;
		},
		lastEditFormattedTimeAgo() {
			return this.data ?
				moment( this.data.lastEditTimestamp * 1000 ).fromNow() :
				NO_DATA_CHARACTER;
		},
		longestEditingStreakCount() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			}
			const bestStreakDaysLocalisedCount = this.$filters.convertNumber(
				this.data.longestEditingStreak.datePeriod.days
			);
			return this.$i18n(
				'growthexperiments-homepage-impact-recent-activity-streak-count-text',
				bestStreakDaysLocalisedCount
			).text();
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
		longestEditingStreakText() {
			if ( !this.data ) {
				// Don't show the second information paragraph if no datePeriod is available
				return null;
			}
			return this.$i18n(
				'growthexperiments-homepage-impact-scores-best-streak-info-data-text',
				this.userName,
				this.$filters.convertNumber( this.data.longestEditingStreak.datePeriod.days ),
				this.bestStreakFormattedDates
			).text();
		}
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-ScoreCards {
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-gap: 2px;
	// Expand scores stripe over homepage modules padding
	margin: 0 -16px;

	&__link {
		.disabled-visited();
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
