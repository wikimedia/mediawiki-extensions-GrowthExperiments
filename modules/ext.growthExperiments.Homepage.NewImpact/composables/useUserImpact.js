const { computed } = require( 'vue' );
const useMWRestApi = require( './useMWRestApi.js' );
const sum = ( arr ) => arr.reduce( ( x, y ) => x + y, 0 );

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
				lastEditTimestamp,
				longestEditingStreak
			} = data.value;
			const edits = Object.keys( editCountByNamespace )
				.map( ( k ) => editCountByNamespace[ k ] );

			return {
				lastEditTimestamp,
				receivedThanksCount,
				bestStreak: longestEditingStreak,
				contributions: getContribsFromToday( editCountByDay, timeFrame ),
				totalEditsCount: sum( edits )
			};
		} ),
		error
	};
}

module.exports = exports = useUserImpact;
