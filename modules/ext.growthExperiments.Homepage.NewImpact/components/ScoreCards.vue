<template>
	<div class="ext-growthExperiments-ScoreCards">
		<score-card
			:icon="cdxIconEdit"
			:label="$i18n( 'growthexperiments-homepage-impact-scores-edit-count' ).text()"
			:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-edit-count' ).text()"
		>
			<c-text size="md" weight="bold">
				<a
					:href="contributionsUrl"
					class="ext-growthExperiments-ScoreCards__link"
					data-link-id="impact-total-edits"
				>
					{{ totalEditsCount }}
				</a>
			</c-text>
		</score-card>
		<score-card
			:icon="cdxIconUserTalk"
			:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
			:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
			:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-label' ).text()"
			@open="log( 'impact', 'open-thanks-info-tooltip' );"
			@close="log( 'impact', 'close-thanks-info-tooltip' );"
		>
			<c-text
				size="md"
				weight="bold"
			>
				<a
					:href="thanksUrl"
					class="ext-growthExperiments-ScoreCards__link"
					data-link-id="impact-thanks-log"
				>
					{{ receivedThanksCount }}
				</a>
			</c-text>
			<template #info-content>
				<div class="ext-growthExperiments-ScoreCards__scorecard__info">
					<span>
						<cdx-icon
							class="ext-growthExperiments-ScoreCards__scorecard__info__icon"
							:icon="cdxIconInfoFilled"
						></cdx-icon>
						{{ $i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text() }}
					</span>
					<p>
						{{ receivedThanksInfoText }}
					</p>
				</div>
			</template>
		</score-card>
		<score-card
			:icon="cdxIconClock"
			:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-last-edit-text' ).text()"
			:icon-label="$i18n( 'growthexperiments-homepage-impact-recent-activity-last-edit-text' ).text()"
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
			:icon-label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
			:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-streak-info-label' ).text()"
			@open="log( 'impact', 'open-streak-info-tooltip' );"
			@close="log( 'impact', 'close-streak-info-tooltip' );"
		>
			<c-text
				as="span"
				size="md"
				weight="bold">
				{{ longestEditingStreakCount }}
			</c-text>
			<template #info-content>
				<div class="ext-growthExperiments-ScoreCards__scorecard__info">
					<span>
						<cdx-icon
							class="ext-growthExperiments-ScoreCards__scorecard__info__icon"
							:icon="cdxIconInfoFilled"
						></cdx-icon>
						{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text() }}
					</span>
					<p>
						{{ longestEditingStreakFirstParagraph }}
					</p>
					<p>
						{{ longestEditingStreakSecondParagraph }}
					</p>
				</div>
			</template>
		</score-card>
	</div>
</template>

<script>
const moment = require( 'moment' );
const { getIntlLocale } = require( '../../utils/Utils.js' );
const { inject } = require( 'vue' );
const { CdxIcon } = require( '@wikimedia/codex' );
const ScoreCard = require( './ScoreCard.vue' );
const CText = require( '../../vue-components/CText.vue' );
const {
	cdxIconEdit,
	cdxIconUserTalk,
	cdxIconClock,
	cdxIconChart,
	cdxIconInfoFilled
} = require( '../../vue-components/icons.json' );
const { NO_DATA_CHARACTER } = require( '../constants.js' );
// References ComputedUserImpactLookup::MAX_EDITS / MAX_THANKS. If we get exactly this number
// for edit count or thanks count, there are probably more.
const DATA_ROWS_LIMIT = 1000;

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		ScoreCard,
		CText,
		CdxIcon
	},
	props: {
		thanksUrl: {
			type: String,
			default: null
		},
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
		const renderThirdPerson = inject( 'RENDER_IN_THIRD_PERSON' );
		const hasIntl = inject( 'BROWSER_HAS_INTL' );
		const log = inject( '$log' );

		return {
			hasIntl,
			userName,
			cdxIconEdit,
			cdxIconUserTalk,
			cdxIconClock,
			cdxIconChart,
			cdxIconInfoFilled,
			renderThirdPerson,
			log
		};
	},
	computed: {
		totalEditsCount() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			} else if ( this.data.totalEditsCount >= DATA_ROWS_LIMIT ) {
				return this.$i18n( 'growthexperiments-homepage-impact-scores-over-limit' );
			}
			return this.$filters.convertNumber( this.data.totalEditsCount );
		},
		receivedThanksCount() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			} else if ( this.data.receivedThanksCount >= DATA_ROWS_LIMIT ) {
				return this.$i18n( 'growthexperiments-homepage-impact-scores-over-limit' );
			}
			return this.$filters.convertNumber( this.data.receivedThanksCount );
		},
		receivedThanksInfoText() {
			return this.renderThirdPerson ?
				this.$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-text-third-person' ).text() :
				this.$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-text', this.userName ).text();
		},
		lastEditFormattedTimeAgo() {
			if ( this.data && this.data.lastEditTimestamp ) {
				return moment( this.data.lastEditTimestamp * 1000 ).fromNow();
			}
			return NO_DATA_CHARACTER;
		},
		longestEditingStreakCount() {
			if ( this.data && this.data.longestEditingStreak ) {
				const bestStreakDaysLocalisedCount = this.$filters.convertNumber(
					this.data.longestEditingStreak.datePeriod.days
				);
				return this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-streak-count-text',
					bestStreakDaysLocalisedCount
				).text();
			}
			return NO_DATA_CHARACTER;
		},
		bestStreakFormattedDates() {
			if ( this.hasIntl ) {
				const today = new Date(),
					locale = getIntlLocale(),

					yearOnlyFormat = new Intl.DateTimeFormat( locale, { year: 'numeric' } ),

					sameYearFormat = new Intl.DateTimeFormat( locale, { month: 'short', day: 'numeric' } ),

					standardFormat = new Intl.DateTimeFormat( locale, { dateStyle: 'medium' } );

				let { start, end } = this.data.longestEditingStreak.datePeriod;
				start = new Date( start );
				end = new Date( end );

				// Rely on DateTimeFormat.formatRange() for range formatting. It will handle pretty
				// much everything it can be expected to handle: formatting the two dates, de-duplicating
				// shared segments of the dates when reasonable for a given date format, selecting
				// the separator, using a non-Gregorian calendar when appropriate.
				//
				// If the streak is in the current year, don't show the year. Note we can't use
				// Date.getYear() as it is not necessarily the same as the local year.
				if ( yearOnlyFormat.format( end ) === yearOnlyFormat.format( today ) ) {
					return sameYearFormat.formatRange( start, end );
				} else {
					return standardFormat.formatRange( start, end );
				}
			}
			return null;
		},
		longestEditingStreakFirstParagraph() {
			return this.renderThirdPerson ?
				this.$i18n( 'growthexperiments-homepage-impact-scores-best-streak-info-text-third-person' ).text() :
				this.$i18n( 'growthexperiments-homepage-impact-scores-best-streak-info-text', this.userName ).text();
		},
		longestEditingStreakSecondParagraph() {
			// Show the second information paragraph only if bestStreakFormattedDates is computed.
			if ( this.bestStreakFormattedDates ) {
				let args = [ this.bestStreakFormattedDates ];
				let message = null;
				if ( this.data.longestEditingStreak.datePeriod.days === 1 ) {
					message = 'growthexperiments-homepage-impact-scores-best-streak-info-data-text-single-day';
				} else {
					message = 'growthexperiments-homepage-impact-scores-best-streak-info-data-text';
					args = [ this.$filters.convertNumber( this.data.longestEditingStreak.datePeriod.days ), ...args ];
				}

				if ( this.renderThirdPerson ) {
					message += '-third-person';
				} else {
					args = [ this.userName, ...args ];
				}
				// The following messages are used here:
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text-single-day
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text-single-day-third-person
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text-third-person
				return this.$i18n( message, ...args ).text();
			}
			return null;
		}
	}
};
</script>
