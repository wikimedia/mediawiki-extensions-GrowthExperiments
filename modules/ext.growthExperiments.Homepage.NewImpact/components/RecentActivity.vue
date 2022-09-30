<template>
	<div class="ext-growthExperiments-RecentActivity">
		<div class="ext-growthExperiments-RecentActivity__streak">
			<div class="ext-growthExperiments-RecentActivity__streak__highlight">
				<c-text
					as="span"
					weight="bold"
					size="x-large"
					class="ext-growthExperiments-RecentActivity__streak__highlight__number"
				>
					{{ localisedContribsCount }}
				</c-text>
				<c-text
					as="span"
					size="medium"
					class="ext-growthExperiments-RecentActivity__streak__highlight__text"
				>
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-contribs-count-text', contribsCount ) }}
				</c-text>
			</div>
			<div class="ext-growthExperiments-RecentActivity__streak__graphic">
				<streak-graph
					:columns="timeFrame"
					:get-column-value-fn="getStreakColumnValue"
					:get-column-title-fn="getStreakColumnTitle"
					:start-label="startLabel"
					:end-label="endLabel"
				></streak-graph>
			</div>
		</div>
		<div v-if="data.lastEditTimestamp" class="ext-growthExperiments-RecentActivity__info">
			<div class="ext-growthExperiments-RecentActivity__info__box">
				<c-text
					size="medium"
					weight="hairline"
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
			<div class="ext-growthExperiments-RecentActivity__info__box">
				<c-text
					size="medium"
					weight="hairline"
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
	</div>
</template>

<script>
const moment = require( 'moment' );
const CText = require( '../../vue-components/CText.vue' );
const StreakGraph = require( './StreakGraph.vue' );

/**
 * Given a contributions object consisting of date strings
 * as keys and the number of edits per day as values, calculate
 * the best streak of edits. If there are not consecutive days with
 * edits the most recent day with edits will be considered the best
 * streak.
 *
 * @param {Object} contribDays
 * @return {{values:Array<string>,count:number}} Array values are ISO date strings. count
 * contains the number of edits for the best streak
 */
const getBestStreak = ( contribDays ) => {
	const streaks = {};
	let streakOffset = 0;
	let bestStreakCounter = 0;
	let bestStreakStartDate = '';
	const hasContribs = contribDays.length > 0;
	const bestStreak = {
		values: hasContribs ? [ contribDays[ contribDays.length - 1 ] ] : undefined,
		count: hasContribs ? 1 : 0
	};
	for ( const i of contribDays.keys() ) {
		if ( new Date( contribDays[ i + 1 ] ) - new Date( contribDays[ i ] ) === 86400000 ) {
			if ( streaks[ contribDays[ i - streakOffset ] ] ) {
				streaks[ contribDays[ i - streakOffset ] ].values.push( contribDays[ i + 1 ] );
				streaks[ contribDays[ i - streakOffset ] ].count++;
				if ( streaks[ contribDays[ i - streakOffset ] ].count > bestStreakCounter ) {
					bestStreakStartDate = contribDays[ i - streakOffset ];
					bestStreakCounter = streaks[ contribDays[ i - streakOffset ] ].count;
				}
				streakOffset++;
			} else {
				streaks[ contribDays[ i ] ] = {
					values: [
						contribDays[ i ],
						contribDays[ i + 1 ]
					],
					count: 2
				};
				bestStreakCounter = Math.max( bestStreakCounter, 2 );
				streakOffset = 1;
			}
		} else {
			streakOffset = 0;
		}
	}

	return streaks[ bestStreakStartDate ] || bestStreak;
};

/**
 * Convenience method to get the start and end
 * values from a contributions object
 *
 * @param {Object} contribDays
 * @return {Array}
 */
const getStreakRange = ( contribDays ) => {
	const { values } = getBestStreak( contribDays );
	if ( !values ) {
		return [];
	}
	return [ values[ 0 ], values[ values.length - 1 ] ];
};

/**
 * Given a contributions object consisting of date strings
 * as keys and the number of edits per day as values, fill
 * two arrays (keys, entries) with empty contribution days.
 * The keys array will contain date strings starting from today - timeFrameInDays
 * until today (ascending order). The entries array will contain
 * the matching value for each day.
 *
 * @param {Object} contribDays
 * @param {number} timeFrameInDays
 * @return {{keys: Array<string>, entries: Array<number>}}
 */
const getContribsFromToday = ( contribDays, timeFrameInDays ) => {
	const today = new Date();
	const withoutTime = ( date ) => {
		const [ withoutT ] = date.toISOString().split( 'T' );
		return withoutT;
	};
	const subtractDays = ( date, days ) => {
		date.setDate( date.getDate() - days );
	};
	const entries = [];
	const keys = [];
	for ( const defaultValue of Array( timeFrameInDays ).fill( 0 ) ) {
		const dateKey = withoutTime( today );
		keys.push( dateKey );
		entries.push( contribDays[ dateKey ] || defaultValue );
		subtractDays( today, 1 );
	}

	return {
		keys: keys.slice().reverse(),
		entries: entries.slice().reverse()
	};
};

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		StreakGraph
	},
	props: {
		data: {
			type: Object,
			default: null
		},
		timeFrame: {
			type: Number,
			required: true
		},
		dateFormat: {
			type: String,
			default: 'MMM D'
		}
	},
	computed: {
		minContribs() {
			return Math.min( ...this.contribs.entries );
		},
		maxContribs() {
			return Math.max( ...this.contribs.entries );
		},
		contribs() {
			return getContribsFromToday( this.data.editCountByDay, this.timeFrame );
		},
		contribsCount() {
			return this.contribs.entries.reduce( ( x, y ) => x + y, 0 );
		},
		localisedContribsCount() {
			return mw.language.convertNumber( this.contribsCount );
		},
		endLabel() {
			return moment().format( this.dateFormat );
		},
		startLabel() {
			return moment()
				.subtract( this.timeFrame - 1, 'days' )
				.format( this.dateFormat );
		},
		lastEditMoment() {
			return moment( this.data.lastEditTimestamp * 1000 );
		},
		lastEditFormattedTimeAgo() {
			return this.lastEditMoment.fromNow();
		},
		lastEditFormattedDate() {
			return this.lastEditMoment.format( this.dateFormat );
		},
		bestStreakFormattedDates() {
			const [ start, end ] = getStreakRange( Object.keys( this.data.editCountByDay ) )
				.map( ( d ) => moment( d ) );
			if ( !start || !end ) {
				return '';
			}
			if ( start.isSame( end ) ) {
				return `${start.format( this.dateFormat )}`;
			}
			if ( start.isSame( end, 'month' ) ) {
				return `${start.format( this.dateFormat )} — ${end.format( 'D' )}`;
			}
			return `${start.format( this.dateFormat )} — ${end.format( this.dateFormat )}`;
		},
		bestStreakDaysLocalisedCount() {
			// Assumes object keys are incremental date strings
			const { count } = getBestStreak( Object.keys( this.data.editCountByDay ) );
			return mw.language.convertNumber( count );
		}
	},
	methods: {
		getStreakColumnValue( index ) {
			const value = this.contribs.entries[ index ];
			const range = Math.max( this.maxContribs - this.minContribs, 1 );
			const scale = 100 / range;
			return value * scale;
		},
		getStreakColumnTitle( index ) {
			const date = moment( this.contribs.keys[ index ] ).format( this.dateFormat );
			if ( this.contribs.entries[ index ] > 0 ) {
				return this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-streak-data-text',
					mw.language.convertNumber( this.contribs.entries[ index ] ),
					date
				);
			}

			return date;
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-RecentActivity {
	display: flex;
	flex-direction: column;

	&__streak,
	&__info {
		display: flex;
	}

	&__streak__highlight {
		display: flex;
		flex-direction: column;
		margin-right: 1.2em;
	}

	&__info__box {
		flex: 1;
	}

	&__streak__graphic {
		// Half of the x-large number of edits line-height
		padding-top: 0.8em;
		flex: 5;
	}
}
</style>
