'use strict';

/*
 * CodexModule generates codex.js at ResourceLoader time, and CLiveRegion.vue and
 * InterestSelector.vue are mapped in from another directory, so none of them exist here on
 * disk for jest to resolve. The interest selector is stubbed: these tests only cover the
 * article count.
 */
jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );
jest.mock( './CLiveRegion.vue', () => require( '../vue-components/CLiveRegion.vue' ), { virtual: true } );
jest.mock( './InterestSelector.vue', () => ( {
	name: 'InterestSelector',
	props: [ 'chips' ],
	emits: [ 'update:chips' ],
	render: () => null,
} ), { virtual: true } );

const mockFetchTasks = jest.fn();
jest.mock( 'ext.growthExperiments.DataStore', () => ( {
	newcomerTasks: {
		filters: {
			getSelectedInterests: () => [ 'Botany' ],
			getTaskTypesQuery: () => [],
		},
		api: { fetchTasks: mockFetchTasks },
	},
} ), { virtual: true } );

const { mount, flushPromises } = require( '@vue/test-utils' );
const InterestSelectorDialog = require( './InterestSelectorDialog.vue' );

const textSelector = '.cdx-dialog__footer__text .ext-growthExperiments-CLiveRegion__text';
const announcerSelector = '.cdx-dialog__footer__text [role="status"]';
const findingMessage = 'growthexperiments-home-suggestededits-filters-finding';
const countMessage = 'growthexperiments-home-suggestededits-difficulty-filters-subtitle-count';
const noneMessage = 'growthexperiments-home-suggestededits-filters-none';
const hintSelector = '.cdx-dialog__footer__text a';

// Render the message as a link, so that the tests can click it.
const linkDirective = ( el, binding ) => {
	el.innerHTML = `<a href="${ binding.value[ 0 ] }">${ binding.arg }</a>`;
};

/**
 * Make a count request that the test resolves.
 *
 * @return {{request: Promise, resolve: Function}}
 */
function makeRequest() {
	let resolve;
	const request = new Promise( ( res ) => {
		resolve = ( count ) => res( { count } );
	} );
	request.abort = jest.fn();
	return { request, resolve };
}

describe( 'InterestSelectorDialog', () => {
	let firstRequest, wrapper;

	beforeEach( () => {
		firstRequest = makeRequest();
		mockFetchTasks.mockReset().mockReturnValueOnce( firstRequest.request );
	} );

	afterEach( () => {
		wrapper.unmount();
	} );

	// CdxDialog changes the elements around it when it opens and closes, so it needs a
	// document. It does this one tick after mount, so wait for it before the test closes it.
	async function mountOpenDialog( props = {} ) {
		wrapper = mount( InterestSelectorDialog, {
			props: Object.assign( { open: true }, props ),
			attrs: { renderInPlace: true },
			attachTo: document.body,
			global: { directives: { 'i18n-html': linkDirective } },
		} );
		await flushPromises();
	}

	it( 'shows that it is finding suggested edits in the footer before the count is known', async () => {
		await mountOpenDialog();
		expect( wrapper.find( announcerSelector ).exists() ).toBe( true );
		expect( wrapper.find( textSelector ).text() ).toBe( findingMessage );
	} );

	it( 'shows the count when it is known', async () => {
		await mountOpenDialog();
		firstRequest.resolve( 5 );
		await flushPromises();
		expect( wrapper.find( textSelector ).text() ).toBe( countMessage );
	} );

	describe( 'when no suggested edits match', () => {
		it( 'links to the types of edits dialog after the status', async () => {
			await mountOpenDialog( { switchable: true } );
			firstRequest.resolve( 0 );
			await flushPromises();

			expect( wrapper.find( textSelector ).text() ).toBe( noneMessage );
			const link = wrapper.find( hintSelector );
			expect( link.text() ).toBe(
				'growthexperiments-home-suggestededits-interest-filters-none-hint',
			);
			// Screen readers announce only the status.
			expect( wrapper.find( '.ext-growthExperiments-CLiveRegion a' ).exists() ).toBe( false );
			// A space separates the hint from the status.
			expect(
				wrapper.find( '.ext-growthExperiments-interest-selector-dialog__switch-hint' )
					.element.textContent,
			).toMatch( /^ \S/ );
		} );

		it( 'asks the parent to switch to the types of edits dialog when the user clicks the link', async () => {
			await mountOpenDialog( { switchable: true } );
			firstRequest.resolve( 0 );
			await flushPromises();

			await wrapper.find( hintSelector ).trigger( 'click' );

			expect( wrapper.emitted( 'switch' ) ).toHaveLength( 1 );
		} );

		it( 'shows only the status when the parent cannot switch dialogs', async () => {
			await mountOpenDialog();
			firstRequest.resolve( 0 );
			await flushPromises();

			expect( wrapper.find( textSelector ).text() ).toBe( noneMessage );
			expect( wrapper.find( hintSelector ).exists() ).toBe( false );
		} );

		it( 'does not link to the other dialog when suggested edits match', async () => {
			await mountOpenDialog( { switchable: true } );
			firstRequest.resolve( 5 );
			await flushPromises();

			expect( wrapper.find( hintSelector ).exists() ).toBe( false );
		} );
	} );

	describe( 'when the dialog closes', () => {
		it( 'stops a count request that has not finished', async () => {
			await mountOpenDialog();
			await wrapper.setProps( { open: false } );
			expect( firstRequest.request.abort ).toHaveBeenCalled();
		} );

		it( 'shows that it is finding suggested edits, not the old count, when it opens again', async () => {
			await mountOpenDialog();
			firstRequest.resolve( 5 );
			await flushPromises();

			const secondRequest = makeRequest();
			mockFetchTasks.mockReturnValueOnce( secondRequest.request );
			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );

			expect( wrapper.find( textSelector ).text() ).toBe( findingMessage );
		} );
	} );
} );
