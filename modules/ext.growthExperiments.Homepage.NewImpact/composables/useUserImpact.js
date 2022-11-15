const { computed } = require( 'vue' );
const useMWRestApi = require( './useMWRestApi.js' );
const sum = ( arr ) => arr.reduce( ( x, y ) => x + y, 0 );

/**
 * Subtract the days from the given date. It mutates
 * the original object.
 *
 * @param {Date} date
 * @param {number} days
 */
const subtractDays = ( date, days ) => {
	date.setDate( date.getDate() - days );
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
 * @return {{lastEditTimestamp: number, receivedThanksCount: number, longestEditingStreak: Object, contributions:Object, totalEditsCount:number}}
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
				receivedThanksCount,
				editCountByDay,
				lastEditTimestamp,
				longestEditingStreak,
				totalEditsCount,
				dailyTotalViews,
				topViewedArticles,
				recentEditsWithoutPageviews
			} = data.value;

			const toPageviewsArray = ( viewsByDay ) => {
				// Fall back to empty array if no page view data (clock icon scenario)
				return Object.keys( viewsByDay || [] ).map( ( key ) => ( {
					date: new Date( key ),
					views: viewsByDay[ key ]
				} ) );
			};

			/**
			 * Build an array of articles for use in NewImpact/App.vue.
			 *
			 * @param {Object} articleDataObject
			 * @return {
			 * {image: {altText: *, href: *},
			 * href: *,
			 * title: *,
			 * views: {entries: *|{date: *, views: *}[],count: *|null, href: *}}[]
			 * }
			 */
			function buildArticlesList( articleDataObject ) {
				return Object.keys( articleDataObject ).map( ( articleTitle ) => {
					const title = new mw.Title( articleTitle );
					const articleData = articleDataObject[ articleTitle ];
					// Fall back to empty array if no page view data (clock icon scenario)
					const articleViewsByDay = Object.keys( articleData.views || [] );
					const viewsCount = articleViewsByDay
						.map( ( day ) => articleData.views[ day ] )
						.reduce( ( x, y ) => x + y, 0 );

					return {
						title: title.getNameText(),
						href: title.getUrl(),
						views: {
							href: articleData.pageviewsUrl,
							count: articleViewsByDay.length > 0 ? viewsCount : null,
							entries: toPageviewsArray( articleData.views )
						},
						image: {
							href: articleData.imageUrl,
							// TODO add captions as thumbnail alt text T322319
							altText: title.getNameText()
						}
					};
				} );
			}

			const articles = buildArticlesList( topViewedArticles ).concat(
				buildArticlesList( recentEditsWithoutPageviews ) );
			const views = toPageviewsArray( dailyTotalViews );

			return {
				articles,
				lastEditTimestamp,
				receivedThanksCount,
				longestEditingStreak,
				totalEditsCount,
				contributions: getContribsFromToday( editCountByDay, timeFrame ),
				dailyTotalViews: {
					entries: views,
					count: sum( views.map( ( view ) => view.views ) )
				}
			};
		} ),
		error: computed( () => {
			if ( !error.value ) {
				return;
			}
			if ( error.value.xhr && error.value.xhr.responseJSON ) {
				return error.value.xhr.responseJSON;
			}
			return error.value;
		} )
	};
}

module.exports = exports = useUserImpact;
