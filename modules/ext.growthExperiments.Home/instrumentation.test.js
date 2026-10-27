'use strict';

jest.mock( './HomeInstrumentation.js', () => ( { useHomeInstrument: jest.fn() } ) );

/**
 * Stand-in for PerformanceObserver, which jsdom does not provide. observe() gives the
 * buffered paint entries to the callback at once.
 */
class PerformanceObserverMock {
	constructor( callback ) {
		this.callback = callback;
		this.disconnect = jest.fn();
	}

	observe() {
		const entries = PerformanceObserverMock.entries;
		this.callback( {
			getEntriesByName: ( name ) => entries.filter( ( entry ) => entry.name === name ),
		}, this );
	}
}

/**
 * Let the pending promise callbacks run.
 *
 * @return {Promise<void>}
 */
function settle() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );
}

/**
 * Run instrumentation.js and return the instrumentation it registers.
 *
 * @return {Function}
 */
function loadInstrumentation() {
	let setupInstrumentation;
	jest.isolateModules( () => {
		const { useHomeInstrument } = require( './HomeInstrumentation.js' );
		require( './instrumentation.js' );
		setupInstrumentation = useHomeInstrument.mock.calls[ 0 ][ 0 ];
	} );
	return setupInstrumentation;
}

/**
 * @param {Object} eventSender
 * @return {Object}
 */
function readyContext( eventSender ) {
	const readyCall = eventSender.send.mock.calls.find( ( [ action ] ) => action === 'ready' );
	return JSON.parse( readyCall[ 1 ].action_context );
}

describe( 'Home instrumentation', () => {
	beforeEach( () => {
		// eslint-disable-next-line n/no-unsupported-features/node-builtins -- browser global in jsdom
		window.PerformanceObserver = PerformanceObserverMock;
		PerformanceObserverMock.supportedEntryTypes = [ 'paint' ];
		PerformanceObserverMock.entries = [
			{ name: 'first-paint', startTime: 100.2 },
			{ name: 'first-contentful-paint', startTime: 345.6 },
		];
		jest.spyOn( performance, 'now' ).mockReturnValue( 789.4 );
	} );

	afterEach( () => {
		// eslint-disable-next-line n/no-unsupported-features/node-builtins -- browser global in jsdom
		delete window.PerformanceObserver;
		delete document.visibilityState;
		jest.restoreAllMocks();
	} );

	it( 'sends page_visit and then ready with both ready times', async () => {
		const setupInstrumentation = loadInstrumentation();
		const eventSender = { send: jest.fn() };

		setupInstrumentation( eventSender );
		await settle();

		expect( eventSender.send.mock.calls.map( ( [ action ] ) => action ) )
			.toEqual( [ 'page_visit', 'ready' ] );
		// eslint-disable-next-line camelcase
		expect( readyContext( eventSender ) ).toEqual( { ready_ms: 346, js_ready_ms: 789 } );
	} );

	it( 'measures the JS ready time when the module runs, not when a sender registers', async () => {
		const setupInstrumentation = loadInstrumentation();
		performance.now.mockReturnValue( 5000 );
		const eventSender = { send: jest.fn() };

		setupInstrumentation( eventSender );
		await settle();

		expect( readyContext( eventSender ).js_ready_ms ).toBe( 789 );
	} );

	it( 'gives the same values to every event sender', async () => {
		const setupInstrumentation = loadInstrumentation();
		const healthSender = { send: jest.fn() };
		const experimentSender = { send: jest.fn() };

		setupInstrumentation( healthSender );
		setupInstrumentation( experimentSender );
		await settle();

		expect( readyContext( experimentSender ) ).toEqual( readyContext( healthSender ) );
	} );

	it( 'sends ready_ms as null when the page is in a background tab', async () => {
		Object.defineProperty( document, 'visibilityState', { value: 'hidden', configurable: true } );
		const setupInstrumentation = loadInstrumentation();
		const eventSender = { send: jest.fn() };

		setupInstrumentation( eventSender );
		await settle();

		// eslint-disable-next-line camelcase
		expect( readyContext( eventSender ) ).toEqual( { ready_ms: null, js_ready_ms: 789 } );
	} );

	it( 'sends ready_ms as null when the browser does not support paint timing', async () => {
		PerformanceObserverMock.supportedEntryTypes = undefined;
		const setupInstrumentation = loadInstrumentation();
		const eventSender = { send: jest.fn() };

		setupInstrumentation( eventSender );
		await settle();

		// eslint-disable-next-line camelcase
		expect( readyContext( eventSender ) ).toEqual( { ready_ms: null, js_ready_ms: 789 } );
	} );
} );
