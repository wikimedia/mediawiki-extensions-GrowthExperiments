'use strict';

const { effectScope } = require( 'vue' );
const { flushPromises } = require( '@vue/test-utils' );
const useSuggestedEditsCount = require( './useSuggestedEditsCount.js' );

const FINDING_MESSAGE = 'growthexperiments-home-suggestededits-filters-finding';
const COUNT_MESSAGE = 'growthexperiments-home-suggestededits-difficulty-filters-subtitle-count';

/**
 * Make a count request that the test settles.
 *
 * @return {{request: Promise, resolve: Function, reject: Function}}
 */
function makeRequest() {
	let resolve, reject;
	const request = new Promise( ( res, rej ) => {
		resolve = ( count ) => res( { count } );
		reject = rej;
	} );
	request.abort = jest.fn();
	return { request, resolve, reject };
}

describe( 'useSuggestedEditsCount', () => {
	let scope, fetchCount, originalMessage;

	/**
	 * @return {Object} the result of useSuggestedEditsCount()
	 */
	function setup() {
		scope = effectScope();
		return scope.run( () => useSuggestedEditsCount( fetchCount ) );
	}

	beforeEach( () => {
		fetchCount = jest.fn();
		originalMessage = mw.message.getMockImplementation();
		mw.message.mockImplementation( ( key, ...params ) => ( {
			text: () => [ key, ...params ].join( ' ' ),
		} ) );
	} );

	afterEach( () => {
		scope.stop();
		mw.message.mockImplementation( originalMessage );
	} );

	it( 'starts idle, with no message', () => {
		const { status, message } = setup();
		expect( status.value ).toBe( 'idle' );
		expect( message.value ).toBe( '' );
	} );

	it( 'shows that it is finding suggested edits, then the count', async () => {
		const pending = makeRequest();
		fetchCount.mockReturnValue( pending.request );
		const { status, count, message, update } = setup();

		update( [ 'copyedit' ] );
		expect( fetchCount ).toHaveBeenCalledWith( [ 'copyedit' ] );
		expect( status.value ).toBe( 'loading' );
		expect( message.value ).toBe( FINDING_MESSAGE );

		pending.resolve( 1 );
		await flushPromises();
		expect( status.value ).toBe( 'found' );
		expect( count.value ).toBe( 1 );
		expect( message.value ).toBe( `${ COUNT_MESSAGE } 1` );
	} );

	it( 'shows that it is finding suggested edits while the next count loads', async () => {
		const first = makeRequest();
		fetchCount.mockReturnValueOnce( first.request );
		const { status, count, message, update } = setup();
		update( [ 'copyedit' ] );
		first.resolve( 42 );
		await flushPromises();

		fetchCount.mockReturnValueOnce( makeRequest().request );
		update( [ 'copyedit', 'expand' ] );

		expect( status.value ).toBe( 'loading' );
		expect( count.value ).toBeNull();
		expect( message.value ).toBe( FINDING_MESSAGE );
	} );

	it( 'ignores a response to an older selection', async () => {
		const older = makeRequest();
		const newer = makeRequest();
		fetchCount.mockReturnValueOnce( older.request ).mockReturnValueOnce( newer.request );
		const { status, count, update } = setup();

		update( [ 'copyedit' ] );
		update( [ 'copyedit', 'expand' ] );
		expect( older.request.abort ).toHaveBeenCalled();

		older.resolve( 5 );
		await flushPromises();
		expect( status.value ).toBe( 'loading' );

		newer.resolve( 7 );
		await flushPromises();
		expect( count.value ).toBe( 7 );
	} );

	it( 'ignores the rejection of an aborted request', async () => {
		const older = makeRequest();
		const newer = makeRequest();
		fetchCount.mockReturnValueOnce( older.request ).mockReturnValueOnce( newer.request );
		const { status, update } = setup();

		update( [ 'copyedit' ] );
		update( [ 'copyedit', 'expand' ] );
		older.reject( 'abort' );
		await flushPromises();

		expect( status.value ).toBe( 'loading' );
	} );

	it( 'shows no message when the count fails', async () => {
		const failing = makeRequest();
		fetchCount.mockReturnValue( failing.request );
		const { status, message, update } = setup();

		update( [ 'copyedit' ] );
		failing.reject( 'http' );
		await flushPromises();

		expect( status.value ).toBe( 'error' );
		expect( message.value ).toBe( '' );
	} );

	it( 'does not request a count when reset() comes before the debounced call', () => {
		let runDebounced;
		mw.util.debounce.mockImplementationOnce( ( fn ) => ( ...args ) => {
			runDebounced = () => fn( ...args );
		} );
		const { status, update, reset } = setup();

		update( [ 'copyedit' ] );
		reset();
		runDebounced();

		expect( fetchCount ).not.toHaveBeenCalled();
		expect( status.value ).toBe( 'idle' );
	} );

	it( 'stops the request and goes back to idle on reset()', async () => {
		const pending = makeRequest();
		fetchCount.mockReturnValue( pending.request );
		const { status, message, update, reset } = setup();

		update( [ 'copyedit' ] );
		reset();
		expect( pending.request.abort ).toHaveBeenCalled();

		pending.resolve( 5 );
		await flushPromises();
		expect( status.value ).toBe( 'idle' );
		expect( message.value ).toBe( '' );
	} );

	it( 'stops the request when its scope stops', () => {
		const pending = makeRequest();
		fetchCount.mockReturnValue( pending.request );
		const { update } = setup();

		update( [ 'copyedit' ] );
		scope.stop();

		expect( pending.request.abort ).toHaveBeenCalled();
	} );
} );
