// Temporary copy. Replace it with the Codex version of useLiveRegion when it is available.
// Do not use MediaWiki APIs in this file, so that it moves to Codex without changes.
const { ref, computed, watch, toValue, onMounted, onBeforeUnmount } = require( 'vue' );

const DEFAULT_DEBOUNCE_MS = 500;
const DEFAULT_MAX_WAIT_MS = 2000;
// Time between clearing the region and setting the same text again. Screen readers ignore a
// clear and a set that occur in the same frame.
const CLEAR_DELAY_MS = 100;
// Time after mount before the region can announce. Some screen readers do not monitor a region
// that they found in the same moment as its first change.
const READY_DELAY_MS = 100;

/**
 * @typedef {Object} LiveRegion
 * @property {Readonly<Ref<string>>} announcement Text of the region element
 * @property {ComputedRef<Object<string,string>>} attrs Attributes of the region element
 * @property {function(string): void} announce Announce a message, also when it is the same as
 *  the last message
 * @property {function(): void} clear Remove the message from the region, and discard the
 *  messages that wait
 */

/**
 * Announce messages to screen readers through an ARIA live region.
 *
 * The caller renders the region element. Bind `attrs` to it, and use `announcement` as its text.
 * Always render the element. Do not render it conditionally, because screen readers do not
 * announce a change to a region that they did not find before the change.
 *
 * There are two ways to send a message:
 * - `source`: a message that is announced when its value changes. Use it for a status of the
 *   current state, for example a count of results.
 * - `announce()`: a message that is announced each time that it is called, also when it is the
 *   same as the last message. Use it for events, for example "Saved".
 *
 * Messages are debounced, so that a quick series of changes gives one announcement of the last
 * message. An empty message clears the region immediately.
 *
 * Call this function from `setup()`, because it uses lifecycle hooks.
 *
 * @param {Ref<string>|function(): string|string} [source] Message to announce when it changes
 * @param {Object} [options]
 * @param {Ref<string>|function(): string|string} [options.politeness='polite'] How urgently
 *  screen readers announce the message: 'polite' or 'assertive'
 * @param {Ref<number>|function(): number|number} [options.debounce=500] Time in milliseconds
 *  without changes before a message is announced
 * @param {Ref<number>|function(): number|number} [options.maxWait=2000] Maximum time in
 *  milliseconds that a message waits while changes continue
 * @param {boolean} [options.announceInitial=false] Whether to announce the initial value of
 *  `source`. When false, the initial value is in the region but it is not announced.
 * @return {LiveRegion}
 */
function useLiveRegion( source, options = {} ) {
	const {
		politeness = 'polite',
		debounce = DEFAULT_DEBOUNCE_MS,
		maxWait = DEFAULT_MAX_WAIT_MS,
		announceInitial = false,
	} = options;

	const initialMessage = source === undefined || announceInitial ? '' : toValue( source ) || '';
	const announcement = ref( initialMessage );
	let lastAnnounced = initialMessage;
	let isReady = false;
	/** @type {{text: string, force: boolean}|null} */
	let pending = null;
	let debounceTimer = null;
	let maxWaitTimer = null;
	let clearTimer = null;
	let readyTimer = null;
	let readyFrame = null;

	const attrs = computed( () => {
		const isAssertive = toValue( politeness ) === 'assertive';
		return {
			role: isAssertive ? 'alert' : 'status',
			'aria-live': isAssertive ? 'assertive' : 'polite',
			'aria-atomic': 'true',
		};
	} );

	function cancelTimers() {
		clearTimeout( debounceTimer );
		clearTimeout( maxWaitTimer );
		debounceTimer = null;
		maxWaitTimer = null;
	}

	function flush() {
		cancelTimers();
		if ( !pending ) {
			return;
		}
		const { text, force } = pending;
		pending = null;
		// A message that is the same as the last one tells the user nothing new, unless it
		// comes from announce().
		if ( !force && text === lastAnnounced ) {
			return;
		}
		lastAnnounced = text;
		clearTimeout( clearTimer );
		if ( announcement.value === text ) {
			// The DOM does not change when the text stays the same, so clear the region first.
			announcement.value = '';
			clearTimer = setTimeout( () => {
				announcement.value = text;
			}, CLEAR_DELAY_MS );
		} else {
			announcement.value = text;
		}
	}

	function startTimers() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( flush, toValue( debounce ) );
		if ( maxWaitTimer === null ) {
			maxWaitTimer = setTimeout( flush, toValue( maxWait ) );
		}
	}

	/**
	 * Remove the message from the region, and discard the messages that wait.
	 */
	function clear() {
		cancelTimers();
		clearTimeout( clearTimer );
		pending = null;
		lastAnnounced = '';
		announcement.value = '';
	}

	/**
	 * @param {string} text
	 * @param {boolean} force
	 */
	function schedule( text, force ) {
		if ( !text ) {
			clear();
			return;
		}
		pending = { text, force };
		// Before the region is ready, keep only the last message.
		if ( isReady ) {
			startTimers();
		}
	}

	/**
	 * Announce a message, also when it is the same as the last message.
	 *
	 * @param {string} text
	 */
	function announce( text ) {
		schedule( text, true );
	}

	if ( source !== undefined ) {
		watch(
			() => toValue( source ),
			( text ) => schedule( text || '', false ),
			{ immediate: announceInitial },
		);
	}

	onMounted( () => {
		readyFrame = requestAnimationFrame( () => {
			readyTimer = setTimeout( () => {
				isReady = true;
				if ( pending ) {
					startTimers();
				}
			}, READY_DELAY_MS );
		} );
	} );

	onBeforeUnmount( () => {
		cancelAnimationFrame( readyFrame );
		clearTimeout( readyTimer );
		clearTimeout( clearTimer );
		cancelTimers();
	} );

	return {
		announcement: computed( () => announcement.value ),
		attrs,
		announce,
		clear,
	};
}

module.exports = exports = useLiveRegion;
