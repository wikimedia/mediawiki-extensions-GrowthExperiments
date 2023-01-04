const { computed, ref, watch } = require( 'vue' );
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
	// eslint-disable-next-line compat/compat
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
 * Reduce the data points to n by splitting the
 * data collection in equal chunks but the last one and
 * summing each chunk views.
 *
 * @param {Array} items The data points to quantize
 * @param {number} n The number of data points the result will have
 * @return {Array}
 */
const quantizeViews = ( items, n = 6 ) => {
	const chunkSize = Math.ceil( items.length / n );
	if ( !chunkSize ) {
		return items;
	}
	// eslint-disable-next-line compat/compat
	const result = new Array( Math.min( n, items.length ) )
		.fill()
		.map( () => items.splice( 0, chunkSize ) )
		.map( ( chunk ) => {
			// eslint-disable-next-line compat/compat
			return Object.assign( chunk[ 0 ], {
				views: sum( chunk.map( ( item ) => item.views ) )
			} );
		} );
	return result;
};

/**
 * Composable to make use of user impact data.
 *
 * @param {number} userId The user id to be used in the data request the data
 * @param {number} timeFrame The number of days from "now" that the contributions should be counted
 * @return {{
 * lastEditTimestamp: number,
 * receivedThanksCount: number,
 * longestEditingStreak: Object,
 * contributions:Object, totalEditsCount:number
 * }}
 */
function useUserImpact( userId, timeFrame ) {
	const encodedUserId = encodeURIComponent( `#${userId}` );
	const specialPageTitle = mw.config.get( 'wgCanonicalSpecialPageName' );
	const exportedDataConfigKeys = {
		Impact: 'specialimpact',
		Homepage: 'homepagemodules'
	};
	const configKey = exportedDataConfigKeys[ specialPageTitle ];
	const serverSideExportedData = mw.config.get( configKey, {} ).impact;
	const finalData = ref( null );
	const finalError = ref( null );
	if ( serverSideExportedData && serverSideExportedData.impact ) {
		finalData.value = serverSideExportedData.impact;
	} else {
		const { data, error } = useMWRestApi( `/growthexperiments/v0/user-impact/${encodedUserId}` );
		watch( [ data, error ], ( [ dataValue, errorValue ] ) => {
			finalData.value = dataValue;
			finalError.value = errorValue;
		} );
	}

	return {
		data: computed( () => {
			if ( !finalData.value ) {
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
				topViewedArticlesCount,
				recentEditsWithoutPageviews
			} = finalData.value;

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
					return {
						title: title.getMainText(),
						href: title.getUrl(),
						views: {
							href: articleData.pageviewsUrl,
							count: articleData.viewsCount,
							entries: quantizeViews( toPageviewsArray( articleData.views ) )
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

			return {
				articles,
				lastEditTimestamp,
				receivedThanksCount,
				longestEditingStreak,
				totalEditsCount,
				articlesViewsCount: topViewedArticlesCount,
				contributions: getContribsFromToday( editCountByDay, timeFrame ),
				dailyTotalViews: toPageviewsArray( dailyTotalViews )
			};
		} ),
		error: computed( () => {
			if ( !finalError.value ) {
				return;
			}
			if ( finalError.value.xhr && finalError.value.xhr.responseJSON ) {
				return finalError.value.xhr.responseJSON;
			}
			return finalError.value;
		} )
	};
}

module.exports = exports = useUserImpact;
