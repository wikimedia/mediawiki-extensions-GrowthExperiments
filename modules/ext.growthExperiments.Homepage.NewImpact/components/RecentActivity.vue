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
					{{ contribsCount }}
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
					:columns="DEFAULT_STREAK_COLUMNS"
					:data="contribs"
					:get-column-title-fn="getStreakColumnTitle"
					:start-label="startLabel"
					:end-label="endLabel"
				></streak-graph>
			</div>
		</div>
		<div class="ext-growthExperiments-RecentActivity__info">
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
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-streak-count-text', bestStreakDaysCount ) }}
				</c-text>
			</div>
		</div>
	</div>
</template>

<script>
const moment = require( 'moment' );
const CText = require( '../../vue-components/CText.vue' );
const StreakGraph = require( './StreakGraph.vue' );
// REVIEW: the proposed format in designs "Feb 3" is not localised across languages
const DISPLAY_DATE_FORMAT = 'MMM D';
const API_DATE_FORMAT = 'YYYY-MM-DD';
const DEFAULT_STREAK_COLUMNS = 30;
const DEFAULT_STREAK_UNIT = 'days';

const getBestStreak = ( contribDays ) => {
	const streaks = {};
	let streakOffset = 0;
	let bestStreakCounter = 0;
	let bestStreak = '';
	for ( const i of contribDays.reverse().keys() ) {
		if ( new Date( contribDays[ i ] ) - new Date( contribDays[ i + 1 ] ) === 86400000 ) {
			if ( streaks[ contribDays[ i - streakOffset ] ] ) {
				streaks[ contribDays[ i - streakOffset ] ].values.push( contribDays[ i + 1 ] );
				streaks[ contribDays[ i - streakOffset ] ].count++;
				if ( streaks[ contribDays[ i - streakOffset ] ].count > bestStreakCounter ) {
					bestStreak = contribDays[ i - streakOffset ];
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

	return streaks[ bestStreak ];
};

const getStreakRange = ( contribDays ) => {
	const { values } = getBestStreak( contribDays );
	return [ values[ 0 ], values[ values.length - 1 ] ];
};

const splitContribs = ( contribDays ) => {
	const today = moment();
	const entries = [];
	const keys = [];
	for ( const defaultValue of Array( DEFAULT_STREAK_COLUMNS ).fill( 0 ) ) {
		const dateKey = today.format( API_DATE_FORMAT );
		keys.push( dateKey );
		entries.push( contribDays[ dateKey ] || defaultValue );
		today.subtract( 1, DEFAULT_STREAK_UNIT );
	}

	return { keys: keys.reverse(), entries: entries.reverse() };
};

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		StreakGraph
	},
	props: {
		// TODO spread into separated props
		data: {
			type: Object,
			default: null
		}
	},
	setup() {
		return {
			DEFAULT_STREAK_COLUMNS
		};
	},
	computed: {
		contribs() {
			return splitContribs( this.data.editCountByDay );
		},
		contribsCount() {
			return this.contribs.entries.reduce( ( x, y ) => x + y, 0 );
		},
		endLabel() {
			return moment().format( DISPLAY_DATE_FORMAT );
		},
		startLabel() {
			return moment()
				.subtract( DEFAULT_STREAK_COLUMNS - 1, DEFAULT_STREAK_UNIT )
				.format( DISPLAY_DATE_FORMAT );
		},
		lastEditMoment() {
			// Moment constructor expects Unix timestamp in milliseconds
			return moment( this.data.lastEditTimestamp * 1000 );
		},
		lastEditFormattedTimeAgo() {
			// TODO if this.data.lastEditTimestamp > 2 weeks ago
			return this.lastEditMoment.fromNow();
		},
		lastEditFormattedDate() {
			return this.lastEditMoment.format( DISPLAY_DATE_FORMAT );
		},
		bestStreakFormattedDates() {
			const [ start, end ] = getStreakRange( Object.keys( this.data.editCountByDay ) )
				.map( ( d ) => moment( d ) );
			if ( start.isSame( end ) ) {
				// REVIEW show it empty?
				return `${start.format( DISPLAY_DATE_FORMAT )}`;
			}
			return `${start.format( DISPLAY_DATE_FORMAT )} — ${end.format( DISPLAY_DATE_FORMAT )}`;
		},
		bestStreakDaysCount() {
			const { count } = getBestStreak( Object.keys( this.data.editCountByDay ) );
			return count;
		}
	},
	methods: {
		getStreakColumnTitle( index ) {
			const date = moment( this.contribs.keys[ index ] ).format( DISPLAY_DATE_FORMAT );
			if ( this.contribs.entries[ index ] > 0 ) {
				return this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-streak-data-text',
					this.contribs.entries[ index ],
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
