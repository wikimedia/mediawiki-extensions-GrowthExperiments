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
			<template #label-info>
				<c-popover
					:close-icon="cdxIconClose"
				>
					<template #trigger="{ onClick }">
						<cdx-button
							type="quiet"
							class="ext-growthExperiments-ScoreCards__info-button"
							:aria-label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-info-label' ).text()"
							@click="onClick"
						>
							<cdx-icon
								:icon="cdxIconInfo"
							></cdx-icon>
						</cdx-button>
					</template>
					<template #content>
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
					</template>
				</c-popover>
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
		>
			<c-text
				as="span"
				size="md"
				weight="bold">
				{{ longestEditingStreakCount }}
			</c-text>
			<template #label-info>
				<c-popover
					:close-icon="cdxIconClose"
				>
					<template #trigger="{ onClick }">
						<cdx-button
							type="quiet"
							class="ext-growthExperiments-ScoreCards__info-button"
							:aria-label="$i18n( 'growthexperiments-homepage-impact-scores-streak-info-label' ).text()"
							@click="onClick"
						>
							<cdx-icon :icon="cdxIconInfo"></cdx-icon>
						</cdx-button>
					</template>
					<template #content>
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
					</template>
				</c-popover>
			</template>
		</score-card>
	</div>
</template>

<script>
const moment = require( 'moment' );
const { getIntlLocale } = require( '../../utils/Utils.js' );
const { inject } = require( 'vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const ScoreCard = require( './ScoreCard.vue' );
const CText = require( '../../vue-components/CText.vue' );
const CPopover = require( '../../vue-components/CPopover.vue' );
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
// References ComputedUserImpactLookup::MAX_EDITS / MAX_THANKS. If we get exactly this number
// for edit count or thanks count, there are probably more.
const DATA_ROWS_LIMIT = 1000;

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		ScoreCard,
		CPopover,
		CText,
		CdxIcon,
		CdxButton
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
			const today = new Date(),
				locale = getIntlLocale(),
				// eslint-disable-next-line compat/compat
				yearOnlyFormat = new Intl.DateTimeFormat( locale, { year: 'numeric' } ),
				// eslint-disable-next-line compat/compat
				sameYearFormat = new Intl.DateTimeFormat( locale, { month: 'short', day: 'numeric' } ),
				// eslint-disable-next-line compat/compat
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
		},
		longestEditingStreakText() {
			if ( !this.data ) {
				// Don't show the second information paragraph if no datePeriod is available
				return null;
			}
			const message = ( this.data.longestEditingStreak.datePeriod.days === 1 ) ?
				'growthexperiments-homepage-impact-scores-best-streak-info-data-text-single-day' :
				'growthexperiments-homepage-impact-scores-best-streak-info-data-text';
			return this.$i18n(
				message,
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

	&__info-button {
		.codex-icon-only-button( @color-subtle, 24px);
	}
}
</style>
