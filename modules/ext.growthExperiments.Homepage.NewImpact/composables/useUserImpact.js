const { computed } = require( 'vue' );
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
 * @return {{keys: Array<string>, entries: Array<number>, count: number}}
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
	const result = new Array( Math.min( n, items.length ) )
		.fill()
		.map( () => items.splice( 0, chunkSize ) )
		.map( ( chunk ) => {
			return Object.assign( chunk[ 0 ], {
				views: sum( chunk.map( ( item ) => item.views ) )
			} );
		} );
	return result;
};

/**
 * Composable to transform the user impact API response data.
 *
 * @param {number} timeFrame The number of days from "now" that the contributions should be counted
 * @param {Object} initialData The data to initialize the module with.
 * @return {{
 * articles: Array,
 * articlesViewsCount: number,
 * contributions: Object,
 * dailyTotalViews: Array,
 * lastEditTimestamp: number,
 * longestEditingStreak: Object,
 * receivedThanksCount: number,
 * totalEditsCount: number
 * }}
 */
function useUserImpact( timeFrame, initialData ) {
	return computed( () => {
		if ( !initialData ) {
			return;
		}
		const {
			receivedThanksCount,
			editCountByDay,
			lastEditTimestamp,
			longestEditingStreak,
			totalEditsCount,
			dailyTotalViews,
			totalPageviewsCount,
			topViewedArticles,
			recentEditsWithoutPageviews
		} = initialData;

		const toPageviewsArray = ( viewsByDay ) => {
			// Fall back to empty array if no page view data (clock icon scenario)
			// Ensure datestring keys are alphanumerically ordered
			return Object.keys( viewsByDay || [] ).sort().map( ( key ) => ( {
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
			articlesViewsCount: totalPageviewsCount,
			contributions: getContribsFromToday( editCountByDay, timeFrame ),
			dailyTotalViews: toPageviewsArray( dailyTotalViews )
		};
	} );
}

module.exports = exports = useUserImpact;
