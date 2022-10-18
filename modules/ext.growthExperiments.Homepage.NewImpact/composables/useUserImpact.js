const { computed } = require( 'vue' );
const useMWRestApi = require( './useMWRestApi.js' );
const sum = ( arr ) => arr.reduce( ( x, y ) => x + y, 0 );

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
		values: hasContribs ? [ contribDays[ contribDays.length - 1 ] ] : [],
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

	const streak = streaks[ bestStreakStartDate ] || bestStreak;
	streak.range = [ streak.values[ 0 ], streak.values[ streak.values.length - 1 ] ];
	return streak;
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
		entries: entries.slice().reverse(),
		count: sum( entries )
	};
};

/**
 * Composable to make use of user impact data.
 *
 * @param {number} userId The user id to be used in the data request the data
 * @param {number} timeFrame The number of days from "now" that the contributions should be counted
 * @return {{lastEditTimestamp: number, receivedThanksCount: number, bestStreak: Object, contributions:Object, totalEditsCount:number}}
 */
function useUserImpact( userId, timeFrame ) {
	const encodedUserId = encodeURIComponent( `#${userId}` );
	const { data, error } = useMWRestApi( `/growthexperiments/v0/user-impact/${encodedUserId}` );

	return {
		data: computed( () => {
			if ( !data.value ) {
				return;
			}
			const {
				editCountByNamespace,
				receivedThanksCount,
				editCountByDay,
				lastEditTimestamp
			} = data.value;
			const edits = Object.keys( editCountByNamespace )
				.map( ( k ) => editCountByNamespace[ k ] );

			return {
				lastEditTimestamp,
				receivedThanksCount,
				bestStreak: getBestStreak( Object.keys( editCountByDay ) ),
				contributions: getContribsFromToday( editCountByDay, timeFrame ),
				totalEditsCount: sum( edits )
			};
		} ),
		error
	};
}

module.exports = exports = useUserImpact;
