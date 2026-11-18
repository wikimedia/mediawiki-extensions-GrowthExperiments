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
const { CONSTANTS, newcomerTasks } = require( 'ext.growthExperiments.DataStore' );
const InterestFilters = require( '../ext.growthExperiments.DataStore/InterestFilters.js' );

const { api, filters } = newcomerTasks;

// An explicit empty selection: the API ignores the stored interests. The API does not
// use its cache for a request that sends the interests, so the fallback request does not
// replace the cached suggestions of the user.
const NO_INTERESTS = new InterestFilters( { interests: [] } );

// The fallback items for each selection of task types. The API sorts the tasks at
// random when it does not filter by interests, so a new request gives other tasks.
// The user sees the same fallback suggestions until the page reloads.
const fallbackItems = new Map();

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
 * @param {string[]} taskTypes Task type IDs
 * @param {mw.libs.ge.InterestFilters|null} interestFilters
 * @param {number} limit Maximum number of tasks to return
 * @param {string} context Identifies the request in the performance data
 * @return {jQuery.Promise<{count: number, tasks: Object[]}>} An abortable promise
 */
function requestTasks( taskTypes, interestFilters, limit, context ) {
	return api.fetchTasks( taskTypes, interestFilters, {
		// prop=description comes from Wikibase Client, so asking for it on a
		// wiki without that extension fails the whole query rather than just
		// leaving the field out. The module tells us whether to ask.
		getDescription: mw.config.get( 'GESuggestedEditsDescriptionsAvailable' ),
		size: limit,
		context,
	} );
}

/**
 * @param {Object[]} tasks Tasks from the API
 * @param {boolean} isFallback Whether the tasks ignore the interests of the user
 * @return {Object[]} Feed items. A fallback item also gets fallbackTier from
 *   fetchFallbackItems().
 */
function toItems( tasks, isFallback ) {
	// The title, not the page id: a task can carry a null pageId when the search
	// index is ahead of a deletion, or in development setups whose suggestions
	// aren't local articles, and the feed keys its list on this.
	return tasks.map( ( task ) => Object.assign( { id: task.title, isFallback }, task ) );
}

/**
 * Fetch fallback suggested edits, which ignore the interests (T443803). Use them when no
 * suggested edits match the selected interests and task types. First ask for the
 * selected task types. When there are none, ask for all the task types that the user
 * can get, also the medium and hard ones.
 *
 * @param {number} limit Maximum number of items to return
 * @return {Promise<Object[]>} Feed items. Each item has fallbackTier:
 *   'selected_task_types' or 'all_task_types'. Empty when no task matches any task type.
 */
async function fetchFallbackItems( limit ) {
	const requests = [];
	// When the user has no interests, the request for the stored selection already
	// ignored the interests. Do not send it again.
	// The fallback tier goes to the module_shown event. Then the event can tell which
	// request gave the fallback items.
	if ( filters.getSelectedInterests().length ) {
		requests.push( [
			filters.getTaskTypesQuery(),
			'personaldashboard_suggestededits_fallback',
			'selected_task_types',
		] );
	}
	requests.push( [
		CONSTANTS.AVAILABLE_TASK_TYPE_IDS,
		'personaldashboard_suggestededits_fallback_all',
		'all_task_types',
	] );

	for ( const [ taskTypes, context, fallbackTier ] of requests ) {
		// The key is the same for the two requests when the user selected all the task
		// types. Then the second request asks again for the same tasks, so skip it.
		const key = JSON.stringify( taskTypes );
		if ( !fallbackItems.has( key ) ) {
			const result = await requestTasks( taskTypes, NO_INTERESTS, limit, context );
			fallbackItems.set( key, toItems( result.tasks, true ) );
		}
		if ( fallbackItems.get( key ).length ) {
			// Set the tier now, not in the stored items. Two requests can have the same key,
			// and the tier comes from the request that gives the items now.
			return fallbackItems.get( key )
				.map( ( item ) => Object.assign( {}, item, { fallbackTier } ) );
		}
	}
	return [];
}

/**
 * Fetch the viewer's suggested edits, most relevant first. When none match the
 * stored selection, fetch fallback suggestions. One load gives one result, so the
 * feed discards the fallback of a load that a newer load replaces.
 *
 * @param {number} limit Maximum number of items to return
 * @param {jQuery.Promise|Promise} [ready] Wait for this promise before the query. Use it
 *  when a filter preference is still being saved, because the API reads the stored
 *  preference. The feed shows that it loads while it waits. The promise must not reject.
 * @return {Promise<Object[]>} Feed items. Each item has isFallback.
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
		const request = requestTasks(
			filters.getTaskTypesQuery(),
			filters.getFiltersQuery(),
			limit,
			'personaldashboard_suggestededits',
		);
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
		// The count of the stored selection stays the count of the request above,
		// also when the feed shows fallback items.
		lastItems = result.tasks.length ?
			toItems( result.tasks, false ) :
			await fetchFallbackItems( limit );
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
