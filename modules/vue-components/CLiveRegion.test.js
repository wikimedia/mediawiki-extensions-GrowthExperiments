'use strict';

const { mount } = require( '@vue/test-utils' );
const CLiveRegion = require( './CLiveRegion.vue' );

// One animation frame and READY_DELAY_MS of useLiveRegion.
const READY_MS = 16 + 100;
const DEBOUNCE_MS = 500;

const textSelector = '.ext-growthExperiments-CLiveRegion__text';
const announcerSelector = '.ext-growthExperiments-CLiveRegion__announcer';

describe( 'CLiveRegion', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'renders a polite status region by default', () => {
		const wrapper = mount( CLiveRegion );
		expect( wrapper.find( announcerSelector ).attributes() ).toMatchObject( {
			role: 'status',
			'aria-live': 'polite',
			'aria-atomic': 'true',
		} );
	} );

	it( 'renders an assertive alert region when politeness is "assertive"', () => {
		const wrapper = mount( CLiveRegion, { props: { politeness: 'assertive' } } );
		expect( wrapper.find( announcerSelector ).attributes() ).toMatchObject( {
			role: 'alert',
			'aria-live': 'assertive',
		} );
	} );

	it( 'renders only the announcer by default', () => {
		const wrapper = mount( CLiveRegion, { props: { message: '5 articles found' } } );
		expect( wrapper.find( textSelector ).exists() ).toBe( false );
		expect( wrapper.find( announcerSelector ).text() ).toBe( '5 articles found' );
	} );

	describe( 'when "visible" is set', () => {
		it( 'shows the message in an element that screen readers ignore', () => {
			const wrapper = mount( CLiveRegion, {
				props: { message: '5 articles found', visible: true },
			} );
			const text = wrapper.find( textSelector );
			expect( text.attributes( 'aria-hidden' ) ).toBe( 'true' );
			expect( text.text() ).toBe( '5 articles found' );
		} );

		it( 'shows a no-break space while the message is empty', () => {
			const wrapper = mount( CLiveRegion, { props: { visible: true } } );
			expect( wrapper.find( textSelector ).element.textContent ).toBe( '\u00a0' );
		} );

		it( 'shows a change immediately, and announces it after the debounce', async () => {
			const wrapper = mount( CLiveRegion, { props: { visible: true } } );
			jest.advanceTimersByTime( READY_MS );

			await wrapper.setProps( { message: '5 articles found' } );
			expect( wrapper.find( textSelector ).text() ).toBe( '5 articles found' );
			expect( wrapper.find( announcerSelector ).text() ).toBe( '' );

			jest.advanceTimersByTime( DEBOUNCE_MS );
			await wrapper.vm.$nextTick();
			expect( wrapper.find( announcerSelector ).text() ).toBe( '5 articles found' );
		} );
	} );

	// Screen readers only announce changes to a region that they found before the change.
	it( 'keeps the same announcer element when the message changes', async () => {
		const wrapper = mount( CLiveRegion );
		const announcer = wrapper.find( announcerSelector ).element;
		jest.advanceTimersByTime( READY_MS );

		await wrapper.setProps( { message: '5 articles found' } );
		jest.advanceTimersByTime( DEBOUNCE_MS );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( announcerSelector ).element ).toBe( announcer );
		expect( announcer.textContent ).toBe( '5 articles found' );
	} );

	it( 'uses the "debounce" prop', async () => {
		const wrapper = mount( CLiveRegion, { props: { debounce: 100 } } );
		jest.advanceTimersByTime( READY_MS );

		await wrapper.setProps( { message: '5 articles found' } );
		jest.advanceTimersByTime( 100 );
		await wrapper.vm.$nextTick();
		expect( wrapper.find( announcerSelector ).text() ).toBe( '5 articles found' );
	} );

	it( 'exposes announce() and clear()', async () => {
		const wrapper = mount( CLiveRegion );
		jest.advanceTimersByTime( READY_MS );

		wrapper.vm.announce( 'Saved' );
		jest.advanceTimersByTime( DEBOUNCE_MS );
		await wrapper.vm.$nextTick();
		expect( wrapper.find( announcerSelector ).text() ).toBe( 'Saved' );

		wrapper.vm.clear();
		await wrapper.vm.$nextTick();
		expect( wrapper.find( announcerSelector ).text() ).toBe( '' );
	} );
} );
