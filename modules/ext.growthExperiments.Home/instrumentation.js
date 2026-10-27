const { useHomeInstrument } = require( './HomeInstrumentation.js' );

const jsReadyMs = Math.round( performance.now() );

/**
 * @return {Promise<number|null>} Null when the browser has no reliable value
 */
function getFirstContentfulPaint() {
	// A page in a background tab paints only when the user opens the tab.
	if (
		document.visibilityState === 'hidden' ||
		!( PerformanceObserver.supportedEntryTypes || [] ).includes( 'paint' )
	) {
		return Promise.resolve( null );
	}
	return new Promise( ( resolve ) => {
		new PerformanceObserver( ( list, observer ) => {
			const entry = list.getEntriesByName( 'first-contentful-paint' )[ 0 ];
			if ( entry ) {
				observer.disconnect();
				resolve( Math.round( entry.startTime ) );
			}
		} ).observe( { type: 'paint', buffered: true } );
	} );
}

// The page works before JS runs, so the first paint is when the user can act.
const firstContentfulPaint = getFirstContentfulPaint();

/**
 * @param {mw.testKitchen.EventSenderInterface} eventSender
 */
function setupInstrumentation( eventSender ) {
	eventSender.send( 'page_visit' );
	firstContentfulPaint.then( ( readyMs ) => {
		eventSender.send( 'ready', {
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( {
				// eslint-disable-next-line camelcase
				ready_ms: readyMs,
				// eslint-disable-next-line camelcase
				js_ready_ms: jsReadyMs,
			} ),
		} );
	} );
}

useHomeInstrument( setupInstrumentation );
