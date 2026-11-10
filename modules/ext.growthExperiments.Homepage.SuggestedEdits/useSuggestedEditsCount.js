const { computed, onScopeDispose, readonly, ref } = require( 'vue' );

const COUNT_DEBOUNCE_MS = 300;
const FINDING_MESSAGE = 'growthexperiments-home-suggestededits-filters-finding';
const COUNT_MESSAGE = 'growthexperiments-home-suggestededits-difficulty-filters-subtitle-count';
const NONE_MESSAGE = 'growthexperiments-home-suggestededits-filters-none';

/**
 * @typedef {'idle'|'loading'|'found'|'none'|'error'} SuggestedEditsCountStatus
 */

/**
 * @typedef {Object} SuggestedEditsCount
 * @property {Readonly<Ref<SuggestedEditsCountStatus>>} status State of the count
 * @property {Readonly<Ref<number|null>>} count The number of suggested edits, or null when
 *  the status is not "found" or "none"
 * @property {ComputedRef<string>} message Status message for the user. It is empty when the
 *  status is "idle" or "error".
 * @property {function(*): void} update Count the suggested edits for a selection
 * @property {function(): void} reset Stop the count, and go back to the "idle" status
 */

/**
 * Count the suggested edits that a filter selection gives, for the filter dialogs.
 *
 * The status changes to "loading" immediately when update() is called. The request starts
 * after a debounce, so that a quick series of changes gives one request. Only the response
 * to the last update() changes the status: a response to an older selection is ignored.
 *
 * Call this function from `setup()`. The count stops when the component unmounts.
 *
 * @param {function(*): jQuery.Promise|Promise} fetchCount Request the count for a
 *  selection. The promise resolves with an object that has a `count` property. It can have
 *  an `abort()` method.
 * @return {SuggestedEditsCount}
 */
function useSuggestedEditsCount( fetchCount ) {
	const status = ref( 'idle' );
	const count = ref( null );
	// Each update() and reset() increments this. A debounced call or a response that has an
	// older value is for a selection that is not current, so it does nothing.
	let generation = 0;
	let request = null;

	function abortRequest() {
		if ( request && request.abort ) {
			request.abort();
		}
		request = null;
	}

	/**
	 * @param {*} selection
	 * @param {number} requestGeneration
	 */
	function fetch( selection, requestGeneration ) {
		if ( requestGeneration !== generation ) {
			return;
		}
		const thisRequest = fetchCount( selection );
		request = thisRequest;
		thisRequest.then( ( data ) => {
			if ( requestGeneration !== generation ) {
				return;
			}
			request = null;
			count.value = data.count;
			status.value = data.count === 0 ? 'none' : 'found';
		}, () => {
			// GrowthTasksApi logs real errors. An aborted request has an older generation.
			if ( requestGeneration !== generation ) {
				return;
			}
			request = null;
			status.value = 'error';
		} );
	}

	const fetchDebounced = mw.util.debounce( fetch, COUNT_DEBOUNCE_MS );

	/**
	 * Count the suggested edits for a selection.
	 *
	 * @param {*} selection Passed to fetchCount
	 */
	function update( selection ) {
		abortRequest();
		generation++;
		count.value = null;
		status.value = 'loading';
		fetchDebounced( selection, generation );
	}

	/**
	 * Stop the count, and go back to the "idle" status.
	 */
	function reset() {
		abortRequest();
		generation++;
		count.value = null;
		status.value = 'idle';
	}

	const message = computed( () => {
		if ( status.value === 'loading' ) {
			return mw.message( FINDING_MESSAGE ).text();
		}
		if ( status.value === 'found' ) {
			return mw.message( COUNT_MESSAGE, mw.language.convertNumber( count.value ) ).text();
		}
		if ( status.value === 'none' ) {
			return mw.message( NONE_MESSAGE ).text();
		}
		return '';
	} );

	onScopeDispose( reset );

	return {
		status: readonly( status ),
		count: readonly( count ),
		message,
		update,
		reset,
	};
}

module.exports = exports = useSuggestedEditsCount;
