'use strict';

const {
	createHomeModuleInstrumentation,
	useHomeInstrument,
} = require( './HomeInstrumentation.js' );

const MODULE_ID = 'ext.growthExperiments.example';
const FRIENDLY_NAME = 'module_example';

/**
 * Stand-in for IntersectionObserver, which jsdom does not provide. The test fires the
 * callback by hand through trigger().
 */
class IntersectionObserverMock {
	constructor( callback, options ) {
		this.callback = callback;
		this.options = options;
		this.observe = jest.fn();
		this.unobserve = jest.fn();
		IntersectionObserverMock.instances.push( this );
	}

	trigger( intersectionRatio ) {
		this.callback( [ { intersectionRatio } ], this );
	}
}
IntersectionObserverMock.instances = [];

/**
 * @return {Object}
 */
function newEventSender() {
	return { send: jest.fn() };
}

/**
 * @return {Function}
 */
function newInstrumentation() {
	return createHomeModuleInstrumentation( MODULE_ID, FRIENDLY_NAME );
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

describe( 'createHomeModuleInstrumentation', () => {
	beforeEach( () => {
		IntersectionObserverMock.instances = [];
		global.IntersectionObserver = IntersectionObserverMock;
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		delete global.IntersectionObserver;
		jest.restoreAllMocks();
	} );

	it( 'passes the event to every registered event sender', () => {
		const instrumentation = newInstrumentation();
		const healthSender = newEventSender();
		const experimentSender = newEventSender();
		instrumentation( healthSender );
		instrumentation( experimentSender );

		instrumentation.send(
			'click',
			// eslint-disable-next-line camelcase
			{ element_friendly_name: 'module_example_link' },
			[ 'performer_name' ],
		);

		for ( const sender of [ healthSender, experimentSender ] ) {
			expect( sender.send ).toHaveBeenCalledTimes( 1 );
			expect( sender.send ).toHaveBeenCalledWith(
				'click',
				// eslint-disable-next-line camelcase
				{ element_friendly_name: 'module_example_link' },
				[ 'performer_name' ],
			);
		}
	} );

	it( 'uses empty defaults for the interaction data and contextual attributes', () => {
		const instrumentation = newInstrumentation();
		const sender = newEventSender();
		instrumentation( sender );

		instrumentation.send( 'click' );

		expect( sender.send ).toHaveBeenCalledWith( 'click', {}, [] );
	} );

	it( 'replays earlier events in order to an event sender that registers later', () => {
		const instrumentation = newInstrumentation();
		const sender = newEventSender();

		instrumentation.send(
			'click',
			// eslint-disable-next-line camelcase
			{ element_friendly_name: 'module_example_link' },
			[ 'performer_name' ],
		);
		instrumentation.send( 'module_ready' );
		instrumentation( sender );

		expect( sender.send.mock.calls ).toEqual( [
			[
				'click',
				// eslint-disable-next-line camelcase
				{ element_friendly_name: 'module_example_link' },
				[ 'performer_name' ],
			],
			[ 'module_ready', {}, [] ],
		] );
	} );

	it( 'gives each event once to event senders that register at different times', () => {
		const instrumentation = newInstrumentation();
		const earlySender = newEventSender();
		const lateSender = newEventSender();

		instrumentation.send( 'first' );
		instrumentation( earlySender );
		instrumentation.send( 'second' );
		instrumentation( lateSender );
		instrumentation.send( 'third' );

		for ( const sender of [ earlySender, lateSender ] ) {
			expect( sender.send.mock.calls.map( ( [ action ] ) => action ) )
				.toEqual( [ 'first', 'second', 'third' ] );
		}
	} );

	it( 'keeps the events of separate instances apart', () => {
		const first = newInstrumentation();
		const second = createHomeModuleInstrumentation( 'other-id', 'module_other' );
		const sender = newEventSender();
		first.send( 'first' );
		second( sender );

		expect( sender.send ).not.toHaveBeenCalled();
	} );

	it( 'sends a click with the element_friendly_name as a top-level field', () => {
		const instrumentation = newInstrumentation();
		const sender = newEventSender();
		instrumentation( sender );

		instrumentation.sendClick( 'module_example_link' );

		expect( sender.send ).toHaveBeenCalledWith(
			'click',
			// eslint-disable-next-line camelcase
			{ element_friendly_name: 'module_example_link' },
			[],
		);
	} );

	describe( 'sendModuleReady', () => {
		beforeEach( () => {
			jest.spyOn( performance, 'now' ).mockReturnValue( 1234.6 );
		} );

		it( 'sends the rounded ready time as a JSON string', () => {
			const instrumentation = newInstrumentation();
			const sender = newEventSender();
			instrumentation( sender );

			instrumentation.sendModuleReady();

			expect( sender.send ).toHaveBeenCalledWith(
				'module_ready',
				{
					// eslint-disable-next-line camelcase
					action_context: JSON.stringify( { ready_ms: 1235 } ),
					// eslint-disable-next-line camelcase
					element_friendly_name: FRIENDLY_NAME,
				},
				[],
			);
		} );

		it( 'adds the given context to action_context', () => {
			const instrumentation = newInstrumentation();
			const sender = newEventSender();
			instrumentation( sender );

			// eslint-disable-next-line camelcase
			instrumentation.sendModuleReady( { is_mentored: true } );

			expect( JSON.parse( sender.send.mock.calls[ 0 ][ 1 ].action_context ) )
				// eslint-disable-next-line camelcase
				.toEqual( { ready_ms: 1235, is_mentored: true } );
		} );
	} );

	describe( 'recordModuleShownAfterVisibleOnce', () => {
		it( 'does nothing when the module element is missing', () => {
			const instrumentation = newInstrumentation();

			expect( () => instrumentation.recordModuleShownAfterVisibleOnce() ).not.toThrow();
			expect( IntersectionObserverMock.instances ).toHaveLength( 0 );
		} );

		it( 'sends module_shown once, when half of the module is visible', () => {
			document.body.innerHTML = `<div id="${ MODULE_ID }"></div>`;
			const moduleElement = document.getElementById( MODULE_ID );
			const instrumentation = newInstrumentation();
			const sender = newEventSender();
			instrumentation( sender );

			// eslint-disable-next-line camelcase
			instrumentation.recordModuleShownAfterVisibleOnce( { is_mentored: true } );

			expect( IntersectionObserverMock.instances ).toHaveLength( 1 );
			const observer = IntersectionObserverMock.instances[ 0 ];
			expect( observer.options ).toEqual( { threshold: 0.5 } );
			expect( observer.observe ).toHaveBeenCalledWith( moduleElement );

			observer.trigger( 0 );
			observer.trigger( 0.49 );
			expect( sender.send ).not.toHaveBeenCalled();
			expect( observer.unobserve ).not.toHaveBeenCalled();

			observer.trigger( 0.5 );
			expect( sender.send ).toHaveBeenCalledTimes( 1 );
			expect( sender.send ).toHaveBeenCalledWith(
				'module_shown',
				{
					// eslint-disable-next-line camelcase
					action_context: JSON.stringify( { is_mentored: true } ),
					// eslint-disable-next-line camelcase
					element_friendly_name: FRIENDLY_NAME,
				},
				[],
			);
			expect( observer.unobserve ).toHaveBeenCalledWith( moduleElement );
		} );

		it( 'sends module_shown without action_context when there is no context', () => {
			document.body.innerHTML = `<div id="${ MODULE_ID }"></div>`;
			const instrumentation = newInstrumentation();
			const sender = newEventSender();
			instrumentation( sender );

			instrumentation.recordModuleShownAfterVisibleOnce();
			IntersectionObserverMock.instances[ 0 ].trigger( 0.7 );

			expect( sender.send ).toHaveBeenCalledWith(
				'module_shown',
				// eslint-disable-next-line camelcase
				{ element_friendly_name: FRIENDLY_NAME },
				[],
			);
		} );
	} );
} );

describe( 'useHomeInstrument', () => {
	let instrument;

	/**
	 * @param {Object} config
	 */
	function setConfig( config ) {
		mw.config.get.mockImplementation(
			( key, fallback ) => ( key in config ? config[ key ] : fallback ),
		);
	}

	beforeEach( () => {
		instrument = { use: jest.fn() };
		mw.testKitchen.getInstrument = jest.fn().mockReturnValue( instrument );
		mw.loader.using.mockResolvedValue( undefined );
	} );

	afterEach( () => {
		delete mw.testKitchen.getInstrument;
		mw.config.get.mockReset();
		mw.loader.using.mockReset();
		mw.loader.using.mockResolvedValue( undefined );
	} );

	it( 'registers the instrumentation with the Home instrument on Special:Home', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Home' } );
		const instrumentation = jest.fn();

		useHomeInstrument( instrumentation );
		await settle();

		expect( mw.loader.using ).toHaveBeenCalledWith( 'ext.testKitchen' );
		expect( mw.testKitchen.getInstrument ).toHaveBeenCalledWith( 'home-health-metrics' );
		expect( instrument.use ).toHaveBeenCalledWith( instrumentation );
	} );

	it( 'does nothing on other pages', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Homepage' } );

		useHomeInstrument( jest.fn() );
		await settle();

		expect( mw.loader.using ).not.toHaveBeenCalled();
		expect( instrument.use ).not.toHaveBeenCalled();
	} );

	it( 'does nothing when the PersonalDashboard override is active', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Home', wgPersonalDashboardPdoActive: true } );

		useHomeInstrument( jest.fn() );
		await settle();

		expect( mw.loader.using ).not.toHaveBeenCalled();
		expect( instrument.use ).not.toHaveBeenCalled();
	} );

	it( 'does nothing when TestKitchen is not installed', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Home' } );
		mw.loader.using.mockRejectedValue( new Error( 'Unknown module: ext.testKitchen' ) );

		useHomeInstrument( jest.fn() );
		await settle();

		expect( instrument.use ).not.toHaveBeenCalled();
	} );
} );
