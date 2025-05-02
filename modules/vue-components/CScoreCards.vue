<template>
	<div class="ext-growthExperiments-ScoreCards">
		<c-score-card
			v-if="scoreCards.includes( 'edit-count' )"
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
		</c-score-card>
		<c-score-card
			v-if="scoreCards.includes( 'reverted-edit-count' )"
			:icon="cdxIconEditUndo"
			:label="$i18n( 'growthexperiments-homepage-impact-scores-reverted-edit-count' ).text()"
			:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-reverted-edit-count' ).text()"
		>
			<c-text size="md" weight="bold">
				<a
					:href="revertsUrl"
					class="ext-growthExperiments-ScoreCards__link"
					data-link-id="impact-reverted-edits"
				>
					{{ revertedEditsCount }}
				</a>
			</c-text>
		</c-score-card>
		<c-score-card
			v-if="scoreCards.includes( 'thanks-count' )"
			:icon="cdxIconUserTalk"
			:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
			:icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' ).text()"
			:info-header-icon="cdxIconInfoFilled"
			:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-label' ).text()"
			@open="$emit( 'interaction', 'open-thanks-info-tooltip' );"
			@close="$emit( 'interaction', 'close-thanks-info-tooltip' );"
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
					<p>
						{{ receivedThanksInfoText }}
					</p>
				</div>
			</template>
		</c-score-card>
		<c-score-card
			v-if="scoreCards.includes( 'last-edit' )"
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
		</c-score-card>
		<c-score-card
			v-if="scoreCards.includes( 'best-streak' )"
			:icon="cdxIconChart"
			:label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
			:icon-label="$i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text()"
			:info-icon-label="$i18n( 'growthexperiments-homepage-impact-scores-streak-info-label' ).text()"
			:info-header-icon="cdxIconInfoFilled"
			@open="$emit( 'interaction', 'open-streak-info-tooltip' );"
			@close="$emit( 'interaction', 'close-streak-info-tooltip' );"
		>
			<c-text
				as="span"
				size="md"
				weight="bold">
				{{ longestEditingStreakCount }}
			</c-text>
			<template #info-content>
				<div class="ext-growthExperiments-ScoreCards__scorecard__info">
					<p>
						{{ longestEditingStreakFirstParagraph }}
					</p>
					<p>
						{{ longestEditingStreakSecondParagraph }}
					</p>
				</div>
			</template>
		</c-score-card>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const moment = require( 'moment' );
const { getIntlLocale } = require( '../utils/Utils.js' );
const CScoreCard = require( './CScoreCard.vue' );
const CText = require( './CText.vue' );
const {
	cdxIconEdit,
	cdxIconEditUndo,
	cdxIconUserTalk,
	cdxIconClock,
	cdxIconChart,
	cdxIconInfoFilled
} = require( './icons.json' );
const { NO_DATA_CHARACTER } = require( '../ext.growthExperiments.Homepage.Impact/constants.js' );

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CScoreCard,
		CText
	},
	props: {
		/**
		 * Username of the user for whom the data is displayed
		 */
		userName: {
			type: String,
			default: null
		},
		/**
		 * Should the score cards refer to the user in third person? Default is first person.
		 */
		renderThirdPerson: {
			type: Boolean,
			default: false
		},
		/**
		 * Does the browser support Intl?
		 */
		hasIntl: {
			type: Boolean,
			default: false
		},
		/**
		 * List of score cards that should be included
		 *
		 * Supported values:
		 *   * edit-count
		 *   * reverted-edit-count
		 *   * thanks-count
		 *   * last-edit
		 *   * best-streak
		 */
		scoreCards: {
			type: Array,
			default: () => [
				'edit-count',
				'thanks-count',
				'last-edit',
				'best-streak'
			]
		},
		/**
		 * JavaScript representation of the UserImpact PHP object
		 */
		data: {
			type: Object,
			default: null
		}
	},
	emits: [ 'interaction' ],
	setup() {
		const maxEdits = inject( 'IMPACT_MAX_EDITS' );
		const maxThanks = inject( 'IMPACT_MAX_THANKS' );
		return {
			cdxIconEdit,
			cdxIconEditUndo,
			cdxIconUserTalk,
			cdxIconClock,
			cdxIconChart,
			cdxIconInfoFilled,
			maxEdits,
			maxThanks
		};
	},
	computed: {
		totalEditsCount() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			}
			return this.$filters.convertNumber( this.data.totalEditsCount );
		},
		revertedEditsCount() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			}
			return this.$filters.convertNumber( this.data.revertedEditCount );
		},
		receivedThanksCount() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			} else if ( this.data.receivedThanksCount >= this.maxThanks ) {
				return this.$i18n( 'growthexperiments-homepage-impact-scores-over-limit' );
			}
			return this.$filters.convertNumber( this.data.receivedThanksCount );
		},
		receivedThanksInfoText() {
			return this.renderThirdPerson ?
				this.$i18n(
					'growthexperiments-homepage-impact-scores-thanks-info-text-third-person',
					this.$filters.convertNumber( this.maxThanks )
				).text() :
				this.$i18n(
					'growthexperiments-homepage-impact-scores-thanks-info-text',
					'', // used to be the username
					this.$filters.convertNumber( this.maxThanks )
				).text();
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
				// much everything it can be expected to handle: formatting the two dates,
				// de-duplicatingshared segments of the dates when reasonable for a given date
				// format, selecting the separator, using a non-Gregorian calendar when appropriate.
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
				this.$i18n(
					'growthexperiments-homepage-impact-scores-best-streak-info-text-third-person',
					this.$filters.convertNumber( this.maxEdits )
				).text() :
				this.$i18n(
					'growthexperiments-homepage-impact-scores-best-streak-info-text',
					'', // used to be the username
					this.$filters.convertNumber( this.maxEdits )
				).text();
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
					args = [
						this.$filters.convertNumber( this.data.longestEditingStreak.datePeriod.days ),
						...args
					];
				}

				if ( this.renderThirdPerson ) {
					message += '-third-person';
				} else {
					args = [ '', ...args ]; // '' used to be the username
				}
				// The following messages are used here:
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text-single-day
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text-single-day-third-person
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text
				// * growthexperiments-homepage-impact-scores-best-streak-info-data-text-third-person
				return this.$i18n( message, ...args ).text();
			}
			return null;
		},
		contributionsUrl() {
			return mw.util.getUrl( `Special:Contributions/${ this.userName }` );
		},
		revertsUrl() {
			return mw.util.getUrl( `Special:Contributions/${ this.userName }`, {
				tagfilter: 'mw-reverted'
			} );
		},
		thanksUrl() {
			return mw.util.getUrl( 'Special:Log', {
				type: 'thanks',
				page: this.userName
			} );
		}
	}
};
</script>
