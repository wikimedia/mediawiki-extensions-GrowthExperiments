/**
 * @file useSuggestedEditsFeed.js
 *
 * The Suggested Edits feed source: ask list=growthtasks for the viewer's
 * recommended articles and normalize them into feed items. Loading and error
 * state come from the shared feed contract.
 *
 * The query is the Newcomer Homepage's own client, reached through the task
 * store the DataStore module already builds, so the viewer's filters resolve
 * here exactly as they do there.
 */

const { useFeedState } = require( 'ext.personalDashboard.common' );
const { newcomerTasks } = require( 'ext.growthExperiments.DataStore' );

const { api, filters } = newcomerTasks;

// The last items we committed, so an aborted request can hand the feed back
// unchanged rather than blanking it.
let lastItems = [];

// The count of the last load, and the stored selection that the load asked for. A
// filter dialog opens with the stored selection, so it can show this count, and it
// does not have to ask again.
let savedCount = null;

/**
 * @return {string} Identifies the stored selection. It changes when the user saves
 *   other task types or interests.
 */
function getStoredSelectionKey() {
	return JSON.stringify( [ filters.getTaskTypesQuery(), filters.getSelectedInterests() ] );
}

/**
 * Fetch the viewer's suggested edits, most relevant first.
 *
 * @param {number} limit Maximum number of items to return
 * @param {jQuery.Promise|Promise} [ready] Wait for this promise before the query. Use it
 *  when a filter preference is still being saved, because the API reads the stored
 *  preference. The feed shows that it loads while it waits. The promise must not reject.
 * @return {Promise<Object[]>} Feed items
 * @throws {Error} If the task query fails
 */
async function fetchSuggestedEdits( limit, ready ) {
	let result;

	await ready;

	try {
		/*
		 * The store's own queries rather than the raw preferences, so we ask for
		 * the filters TaskSetFiltersFactory builds server-side. CacheDecorator
		 * keeps one task set per user, so a request that disagrees with the
		 * Homepage's evicts it and pays for a fresh search.
		 */
		const request = api.fetchTasks( filters.getTaskTypesQuery(), filters.getFiltersQuery(), {
			// prop=description comes from Wikibase Client, so asking for it on a
			// wiki without that extension fails the whole query rather than just
			// leaving the field out. The module tells us whether to ask.
			getDescription: mw.config.get( 'GESuggestedEditsDescriptionsAvailable' ),
			size: limit,
			context: 'personaldashboard_suggestededits',
		} );
		// The size of the request does not change the count: the API counts a pool of
		// tasks that has a fixed size. A new promise, so that a dialog cannot abort the
		// request of the feed.
		const count = {
			key: getStoredSelectionKey(),
			promise: Promise.resolve( request ).then( ( data ) => ( { count: data.count } ) ),
		};
		savedCount = count;
		count.promise.catch( () => {
			// A dialog that opens after the failure asks for the count itself.
			if ( savedCount === count ) {
				savedCount = null;
			}
		} );
		result = await request;
	} catch ( reason ) {
		// An abort is not a failure: GrowthTasksApi routes it through the same
		// rejection channel with isRealError false, and the Homepage module guards
		// on the same string. Leave the feed as it was rather than replacing it
		// with an error card reading "abort".
		if ( reason === 'abort' ) {
			return lastItems;
		}
		// Everything else rejects with a bare string, and the feed contract renders
		// error.message, so an unwrapped rejection surfaces as "undefined".
		throw new Error( reason );
	}

	// The title, not the page id: a task can carry a null pageId when the search
	// index is ahead of a deletion, or in development setups whose suggestions
	// aren't local articles, and the feed keys its list on this.
	lastItems = result.tasks.map( ( task ) => Object.assign( { id: task.title }, task ) );
	return lastItems;
}

// One state for the whole module: the card and the full view share a single
// teleported component instance, and neither should issue its own request.
const { feedState, load } = useFeedState( fetchSuggestedEdits );

/**
 * Get the count of the stored selection, from the last load.
 *
 * The stored selection changes before the next load gives its count: when the user
 * saves, during the save, when the save fails, and when the next load does not start
 * because a load continues (see useFeedState). Then there is no count.
 *
 * @return {Promise<{count: number}>|null} Resolves with the count, also while the load
 *   continues. Null when no load gave the count of the stored selection.
 */
function getSavedCount() {
	return savedCount && savedCount.key === getStoredSelectionKey() ?
		savedCount.promise :
		null;
}

/**
 * @return {{feedState: Object, load: function(number): Promise<void>,
 *   getSavedCount: function(): (Promise<{count: number}>|null)}} The shared feed
 *   contract for this module (see useFeedState.js), its loader, and the count of the
 *   stored selection.
 */
function useSuggestedEditsFeed() {
	return { feedState, load, getSavedCount };
}

module.exports = { useSuggestedEditsFeed };
