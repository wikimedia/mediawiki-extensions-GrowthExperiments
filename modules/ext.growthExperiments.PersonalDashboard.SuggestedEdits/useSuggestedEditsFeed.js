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

/**
 * Fetch the viewer's suggested edits, most relevant first.
 *
 * @param {number} limit Maximum number of items to return
 * @return {Promise<Object[]>} Feed items
 * @throws {Error} If the task query fails
 */
async function fetchSuggestedEdits( limit ) {
	let result;

	try {
		/*
		 * The store's own queries rather than the raw preferences, so we ask for
		 * the filters TaskSetFiltersFactory builds server-side. CacheDecorator
		 * keeps one task set per user, so a request that disagrees with the
		 * Homepage's evicts it and pays for a fresh search.
		 */
		result = await api.fetchTasks( filters.getTaskTypesQuery(), filters.getFiltersQuery(), {
			// prop=description comes from Wikibase Client, so asking for it on a
			// wiki without that extension fails the whole query rather than just
			// leaving the field out. The module tells us whether to ask.
			getDescription: mw.config.get( 'GESuggestedEditsDescriptionsAvailable' ),
			size: limit,
			context: 'personaldashboard_suggestededits',
		} );
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
 * @return {{feedState: Object, load: function(number): Promise<void>}} The
 *   shared feed contract for this module (see useFeedState.js), and its loader.
 */
function useSuggestedEditsFeed() {
	return { feedState, load };
}

module.exports = { useSuggestedEditsFeed };
