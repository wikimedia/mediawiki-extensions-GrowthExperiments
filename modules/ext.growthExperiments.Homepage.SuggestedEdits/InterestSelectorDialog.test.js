'use strict';

/*
 * CodexModule generates codex.js at ResourceLoader time, and CLiveRegion.vue and
 * InterestSelector.vue are mapped in from another directory, so none of them exist here on
 * disk for jest to resolve. The interest selector is stubbed: these tests change the
 * selection through its v-model.
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
const mockFilters = {
	getSelectedInterests: jest.fn(),
	getTaskTypesQuery: () => [],
	setSelectedInterests: jest.fn(),
};
jest.mock( 'ext.growthExperiments.DataStore', () => ( {
	newcomerTasks: {
		filters: mockFilters,
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
	let firstRequest, wrapper, saveOption, originalApi, originalUser, originalErrorLogger;

	beforeEach( () => {
		firstRequest = makeRequest();
		// Later count requests do not finish, unless a test sets them.
		mockFetchTasks.mockReset()
			.mockImplementation( () => makeRequest().request )
			.mockReturnValueOnce( firstRequest.request );
		mockFilters.getSelectedInterests.mockReset().mockReturnValue( [ 'Botany' ] );
		mockFilters.setSelectedInterests.mockReset();
		saveOption = jest.fn();
		originalApi = mw.Api;
		originalUser = mw.user;
		originalErrorLogger = mw.errorLogger;
		mw.Api = jest.fn( () => ( { saveOption } ) );
		mw.user = Object.assign( {}, originalUser, { options: { set: jest.fn() } } );
		mw.errorLogger = { logError: jest.fn() };
	} );

	afterEach( () => {
		wrapper.unmount();
		mw.Api = originalApi;
		mw.user = originalUser;
		mw.errorLogger = originalErrorLogger;
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

	describe( 'when the parent has the count of the stored selection', () => {
		it( 'shows that count when it opens, without a request', async () => {
			await mountOpenDialog( { getSavedCount: () => Promise.resolve( { count: 0 } ) } );

			expect( mockFetchTasks ).not.toHaveBeenCalled();
			expect( wrapper.find( textSelector ).text() ).toBe( noneMessage );
		} );

		it( 'asks for the count when the selection changes', async () => {
			await mountOpenDialog( { getSavedCount: () => Promise.resolve( { count: 0 } ) } );

			wrapper.findComponent( { name: 'InterestSelector' } ).vm.$emit(
				'update:chips', [ { label: 'Fungi', value: 'Fungi' } ],
			);
			await flushPromises();

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 1 );
			expect( wrapper.find( textSelector ).text() ).toBe( findingMessage );
		} );

		it( 'asks for the count when it opens and the parent does not have it', async () => {
			await mountOpenDialog( { getSavedCount: () => null } );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 1 );
		} );
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

	/**
	 * @param {string[]} titles
	 */
	async function select( titles ) {
		wrapper.findComponent( { name: 'InterestSelector' } ).vm.$emit(
			'update:chips',
			titles.map( ( title ) => ( { label: title, value: title } ) ),
		);
		await flushPromises();
	}

	async function clickDone() {
		await wrapper.find( '.cdx-dialog__footer__primary-action' ).trigger( 'click' );
	}

	describe( 'when the user clicks Done', () => {
		it( 'reports the save and closes before the save ends', async () => {
			saveOption.mockReturnValue( $.Deferred().promise() );
			await mountOpenDialog();
			await select( [ 'Botany', 'Geology' ] );

			await clickDone();

			expect( mockFilters.setSelectedInterests ).toHaveBeenCalledWith( [ 'Botany', 'Geology' ] );
			expect( wrapper.emitted( 'saved' ) ).toHaveLength( 1 );
			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'gives a promise that resolves when the save ends', async () => {
			let endSave;
			saveOption.mockReturnValue( new Promise( ( resolve ) => {
				endSave = resolve;
			} ) );
			await mountOpenDialog();
			await select( [ 'Geology' ] );
			await clickDone();
			const saving = wrapper.emitted( 'saved' )[ 0 ][ 0 ];
			const resolved = jest.fn();
			saving.then( resolved );

			await flushPromises();
			expect( resolved ).not.toHaveBeenCalled();

			endSave( {} );
			await flushPromises();
			expect( resolved ).toHaveBeenCalled();
		} );

		it( 'gives a promise that resolves when the save fails, and logs the failure', async () => {
			saveOption.mockRejectedValue( 'http' );
			await mountOpenDialog();
			await select( [ 'Geology' ] );
			await clickDone();
			const resolved = jest.fn();

			wrapper.emitted( 'saved' )[ 0 ][ 0 ].then( resolved );
			await flushPromises();

			expect( resolved ).toHaveBeenCalled();
			expect( mw.errorLogger.logError ).toHaveBeenCalled();
		} );

		it( 'does not save or report when the selection did not change', async () => {
			await mountOpenDialog();

			await clickDone();

			expect( saveOption ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'saved' ) ).toBeUndefined();
			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );
	} );
} );
