'use strict';

const { defineComponent, h, ref, watch } = require( 'vue' );
const { mount } = require( '@vue/test-utils' );
const useLiveRegion = require( './useLiveRegion.js' );

// One animation frame and READY_DELAY_MS.
const READY_MS = 16 + 100;
const DEBOUNCE_MS = 500;
const MAX_WAIT_MS = 2000;
const CLEAR_DELAY_MS = 100;

/**
 * Mount a component that renders the region of useLiveRegion().
 *
 * @param {*} [source]
 * @param {Object} [options]
 * @return {{wrapper: Object, api: Object, region: function(): Object}}
 */
function mountRegion( source, options ) {
	let api;
	const wrapper = mount( defineComponent( {
		setup() {
			api = useLiveRegion( source, options );
			return () => h( 'span', api.attrs.value, api.announcement.value );
		},
	} ) );
	return { wrapper, api, region: () => wrapper.find( 'span' ) };
}

describe( 'useLiveRegion', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	describe( 'attrs', () => {
		it( 'makes a polite status region by default', () => {
			const { region } = mountRegion();
			expect( region().attributes() ).toMatchObject( {
				role: 'status',
				'aria-live': 'polite',
				'aria-atomic': 'true',
			} );
		} );

		it( 'makes an assertive alert region when politeness is "assertive"', async () => {
			const politeness = ref( 'assertive' );
			const { region } = mountRegion( undefined, { politeness } );
			expect( region().attributes() ).toMatchObject( {
				role: 'alert',
				'aria-live': 'assertive',
			} );

			politeness.value = 'polite';
			await Promise.resolve();
			expect( region().attributes( 'role' ) ).toBe( 'status' );
		} );
	} );

	describe( 'initial message', () => {
		it( 'puts the initial message in the region without a change', () => {
			const { api } = mountRegion( ref( '5 articles found' ) );
			expect( api.announcement.value ).toBe( '5 articles found' );

			jest.advanceTimersByTime( READY_MS + MAX_WAIT_MS );
			expect( api.announcement.value ).toBe( '5 articles found' );
		} );

		it( 'announces the initial message when "announceInitial" is set', () => {
			const { api } = mountRegion( ref( '5 articles found' ), { announceInitial: true } );
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( READY_MS + DEBOUNCE_MS );
			expect( api.announcement.value ).toBe( '5 articles found' );
		} );

		it( 'accepts a getter as source', () => {
			const { api } = mountRegion( () => '5 articles found' );
			expect( api.announcement.value ).toBe( '5 articles found' );
		} );
	} );

	describe( 'before the region is ready', () => {
		it( 'keeps only the last message, and starts the debounce when ready', async () => {
			const message = ref( '' );
			const { api } = mountRegion( message );

			message.value = '1 article found';
			await Promise.resolve();
			message.value = '2 articles found';
			await Promise.resolve();

			jest.advanceTimersByTime( READY_MS + DEBOUNCE_MS - 1 );
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( 1 );
			expect( api.announcement.value ).toBe( '2 articles found' );
		} );
	} );

	describe( 'when the source changes', () => {
		let message, api;

		beforeEach( () => {
			message = ref( '' );
			( { api } = mountRegion( message ) );
			jest.advanceTimersByTime( READY_MS );
		} );

		async function setMessage( text ) {
			message.value = text;
			await Promise.resolve();
		}

		it( 'announces the message after the debounce', async () => {
			await setMessage( '5 articles found' );

			jest.advanceTimersByTime( DEBOUNCE_MS - 1 );
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( 1 );
			expect( api.announcement.value ).toBe( '5 articles found' );
		} );

		it( 'restarts the debounce on each change, and announces only the last message', async () => {
			await setMessage( '1 article found' );
			jest.advanceTimersByTime( DEBOUNCE_MS - 100 );
			await setMessage( '2 articles found' );
			jest.advanceTimersByTime( DEBOUNCE_MS - 100 );
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( 100 );
			expect( api.announcement.value ).toBe( '2 articles found' );
		} );

		it( 'announces after "maxWait" while changes continue', async () => {
			for ( let count = 1; count * 400 < MAX_WAIT_MS; count++ ) {
				await setMessage( `${ count } articles found` );
				jest.advanceTimersByTime( 400 );
				expect( api.announcement.value ).toBe( '' );
			}

			await setMessage( 'last' );
			jest.advanceTimersByTime( 400 );
			expect( api.announcement.value ).toBe( 'last' );
		} );

		it( 'does not announce when the message returns to the last announced one', async () => {
			await setMessage( '5 articles found' );
			jest.advanceTimersByTime( DEBOUNCE_MS );

			await setMessage( '6 articles found' );
			await setMessage( '5 articles found' );
			const setValues = [];
			const stop = watch( api.announcement, ( text ) => setValues.push( text ), { flush: 'sync' } );
			jest.advanceTimersByTime( DEBOUNCE_MS + CLEAR_DELAY_MS );
			await Promise.resolve();
			stop();

			expect( setValues ).toEqual( [] );
			expect( api.announcement.value ).toBe( '5 articles found' );
		} );

		it( 'clears the region immediately when the message is empty', async () => {
			await setMessage( '5 articles found' );
			jest.advanceTimersByTime( DEBOUNCE_MS );

			await setMessage( '6 articles found' );
			await setMessage( '' );
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( MAX_WAIT_MS );
			expect( api.announcement.value ).toBe( '' );
		} );

		it( 'announces a message again after the region was cleared', async () => {
			await setMessage( '5 articles found' );
			jest.advanceTimersByTime( DEBOUNCE_MS );
			await setMessage( '' );

			await setMessage( '5 articles found' );
			jest.advanceTimersByTime( DEBOUNCE_MS );
			expect( api.announcement.value ).toBe( '5 articles found' );
		} );
	} );

	describe( 'announce()', () => {
		let api;

		beforeEach( () => {
			( { api } = mountRegion() );
			jest.advanceTimersByTime( READY_MS );
		} );

		it( 'announces the message after the debounce', () => {
			api.announce( 'Saved' );
			jest.advanceTimersByTime( DEBOUNCE_MS );
			expect( api.announcement.value ).toBe( 'Saved' );
		} );

		it( 'clears the region, then sets the same message again', () => {
			api.announce( 'Saved' );
			jest.advanceTimersByTime( DEBOUNCE_MS );

			api.announce( 'Saved' );
			jest.advanceTimersByTime( DEBOUNCE_MS );
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( CLEAR_DELAY_MS );
			expect( api.announcement.value ).toBe( 'Saved' );
		} );
	} );

	describe( 'clear()', () => {
		it( 'empties the region and discards the messages that wait', () => {
			const { api } = mountRegion();
			jest.advanceTimersByTime( READY_MS );
			api.announce( 'Saved' );
			jest.advanceTimersByTime( DEBOUNCE_MS );

			api.announce( 'Saved again' );
			api.clear();
			expect( api.announcement.value ).toBe( '' );

			jest.advanceTimersByTime( MAX_WAIT_MS );
			expect( api.announcement.value ).toBe( '' );
		} );
	} );

	it( 'cancels its timers when the component unmounts', () => {
		const { wrapper, api } = mountRegion();
		jest.advanceTimersByTime( READY_MS );
		api.announce( 'Saved' );

		wrapper.unmount();
		expect( jest.getTimerCount() ).toBe( 0 );
	} );
} );
