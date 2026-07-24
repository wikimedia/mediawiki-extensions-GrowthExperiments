'use strict';
const { mount, flushPromises } = require( '@vue/test-utils' );
const { nextTick } = require( 'vue' );
const InterestSelector = require( './InterestSelector.vue' );

/**
 * @param {Record<string, any>} responsesByGenerator Maps a distinguishing marker found in the
 *  request params (the generator, or gsrsearch/gpssearch value) to the response to resolve with.
 * @return {{ get: jest.Mock }}
 */
function makeMwApi( responsesByGenerator ) {
	return {
		get: jest.fn( ( params ) => {
			for ( const [ marker, response ] of Object.entries( responsesByGenerator ) ) {
				const matches = params.gsrsearch === marker ||
					params.gpssearch === marker ||
					params.generator === marker;
				if ( matches ) {
					return Promise.resolve( response );
				}
			}
			return Promise.resolve( { query: { pages: [] } } );
		} ),
	};
}

/**
 * @param {number} count
 * @return {{label: string, value: string}[]}
 */
function chipsUpTo( count ) {
	return Array.from( { length: count }, ( _unused, i ) => ( { label: `page${ i }`, value: `page${ i }` } ) );
}

describe( 'InterestSelector', () => {
	it( 'renders i18n-driven placeholder, aria-label and heading text', () => {
		const mwApi = { get: jest.fn().mockResolvedValue( { query: { pages: {} } } ) };

		const wrapper = mount( InterestSelector, {
			props: { chips: [] },
			global: { provide: { mwApi: mwApi } },
		} );

		expect( wrapper.find( 'input' ).attributes( 'placeholder' ) ).toBe(
			'growthexperiments-interest-selector-placeholder',
		);
		expect( wrapper.find( 'input' ).attributes( 'aria-label' ) ).toBe(
			'growthexperiments-interest-selector-a11y-label',
		);
		expect( wrapper.find( '.ext-growthExperiments-interest-selector-related-articles' ).text() ).toBe(
			'growthexperiments-interest-selector-related-articles-heading',
		);
	} );

	it( 'gets related articles for existing chips on startup', async () => {
		const mwApiGet = jest.fn().mockResolvedValue( { query: { pages: [
			{ title: 'page3' },
		] } } );
		const mwApi = { get: mwApiGet };

		const wrapper = mount( InterestSelector, {
			props: {
				chips: [
					{ label: 'page1', value: 'page1' },
					{ label: 'page2', value: 'page2' },
				],
			},
			global: {
				provide: {
					mwApi: mwApi,
				},
			},
		} );
		expect( mwApiGet ).toHaveBeenCalledTimes( 2 );
		expect( mwApiGet ).toHaveBeenCalledWith( {
			action: 'query',
			prop: 'pageimages',
			generator: 'search',
			gsrlimit: '10',
			gsrsearch: 'morelike:page1',
		} );
		expect( mwApiGet ).toHaveBeenCalledWith( {
			action: 'query',
			prop: 'pageimages',
			generator: 'search',
			gsrlimit: '10',
			gsrsearch: 'morelike:page2',
		} );
		await flushPromises();

		expect( wrapper.html() ).toContain( 'page3' );
	} );

	it( 'fetches random related articles when there are no chips', async () => {
		const mwApiGet = jest.fn().mockResolvedValue( { query: { pages: {
			1: { title: 'randomPage' },
		} } } );
		const mwApi = { get: mwApiGet };

		const wrapper = mount( InterestSelector, {
			props: { chips: [] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		expect( mwApiGet ).toHaveBeenCalledWith( {
			action: 'query',
			prop: 'pageimages',
			generator: 'search',
			gsrlimit: 5,
			gsrsearch: 'Wikipedia',
			gsrsort: 'random',
		} );
		expect( wrapper.vm.relatedArticles ).toEqual( [
			{ label: 'randomPage', value: 'randomPage', thumbnail: null },
		] );
	} );

	it( 'excludes already-selected pages from the related articles suggestions', async () => {
		const mwApi = {
			get: jest.fn().mockResolvedValue( { query: { pages: {
				1: { title: 'page1' },
				2: { title: 'page2' },
			} } } ),
		};

		const wrapper = mount( InterestSelector, {
			props: { chips: [ { label: 'page1', value: 'page1' } ] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		expect( wrapper.vm.relatedArticles ).toHaveLength( 1 );
		expect( wrapper.vm.relatedArticles[ 0 ].value ).toBe( 'page2' );
	} );

	it( 'caps related articles at 5 suggestions', async () => {
		const pages = {};
		for ( let i = 0; i < 7; i++ ) {
			pages[ i ] = { title: `page${ i }` };
		}
		const mwApi = { get: jest.fn().mockResolvedValue( { query: { pages: pages } } ) };

		const wrapper = mount( InterestSelector, {
			props: { chips: [ { label: 'seed', value: 'seed' } ] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		expect( wrapper.vm.relatedArticles ).toHaveLength( 5 );
	} );

	it( 'caches related-article lookups per page name', async () => {
		const mwApiGet = jest.fn().mockResolvedValue( { query: { pages: {} } } );
		const mwApi = { get: mwApiGet };

		const wrapper = mount( InterestSelector, {
			props: { chips: [ { label: 'page1', value: 'page1' } ] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();
		expect( mwApiGet ).toHaveBeenCalledTimes( 1 );

		await wrapper.setProps( { chips: [
			{ label: 'page1', value: 'page1' },
			{ label: 'page2', value: 'page2' },
		] } );
		await flushPromises();

		// page1 is served from cache; only page2 triggers a new request.
		expect( mwApiGet ).toHaveBeenCalledTimes( 2 );
		expect( mwApiGet ).toHaveBeenCalledWith( expect.objectContaining( { gsrsearch: 'morelike:page2' } ) );
	} );

	it( 'populates menu items from search results', async () => {
		const mwApi = makeMwApi( {
			prefixsearch: { query: { pages: {
				1: { title: 'Found page', description: 'a description' },
			} } },
		} );

		const wrapper = mount( InterestSelector, {
			props: { chips: [] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		wrapper.vm.inputValue = 'Found';
		wrapper.vm.onUpdateInputValueDebounced( 'Found' );
		await flushPromises();

		expect( wrapper.vm.menuItems ).toEqual( [
			{ label: 'Found page', value: 'Found page', description: 'a description' },
		] );
	} );

	it( 'clears menu items when the search input is cleared', async () => {
		const mwApi = makeMwApi( {} );
		const wrapper = mount( InterestSelector, {
			props: { chips: [] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		wrapper.vm.menuItems = [ { label: 'stale', value: 'stale', description: '' } ];
		wrapper.vm.onUpdateInputValueDebounced( '' );

		expect( wrapper.vm.menuItems ).toEqual( [] );
	} );

	it( 'appends deduplicated results to the menu when loading more', async () => {
		const mwApiGet = jest.fn()
			// Response to the initial "no chips" related-articles fetch on mount.
			.mockResolvedValueOnce( { query: { pages: {} } } )
			// Response to the prefixsearch triggered by typing "page".
			.mockResolvedValueOnce( { query: { pages: {
				1: { title: 'page1', description: '' },
			} } } )
			// Response to the load-more request.
			.mockResolvedValueOnce( { query: { pages: {
				1: { title: 'page1', description: '' },
				2: { title: 'page2', description: '' },
			} } } );
		const mwApi = { get: mwApiGet };

		const wrapper = mount( InterestSelector, {
			props: { chips: [] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		wrapper.vm.inputValue = 'page';
		wrapper.vm.onUpdateInputValueDebounced( 'page' );
		await flushPromises();
		expect( wrapper.vm.menuItems ).toHaveLength( 1 );

		wrapper.vm.onLoadMore();
		await flushPromises();

		// page1 is already in the menu, so only page2 gets appended.
		expect( wrapper.vm.menuItems ).toEqual( [
			{ label: 'page1', value: 'page1', description: '' },
			{ label: 'page2', value: 'page2', description: '' },
		] );
	} );

	it( 'adds a clicked related article as a selected chip', async () => {
		const mwApi = { get: jest.fn().mockResolvedValue( { query: { pages: {
			1: { title: 'relatedPage' },
		} } } ) };

		// Mirror a real `v-model:chips` consumer: sync the prop back on update. Without this,
		// CdxMultiselectLookup's own internal `selected`-reconciliation watcher (triggered by the
		// `selection` update in addCardToSelectedChips) reads back a stale `chips` prop and
		// re-emits `update:chips` with that stale value, clobbering the real one.
		const wrapper = mount( InterestSelector, {
			props: {
				chips: [ { label: 'seed', value: 'seed' } ],
				'onUpdate:chips': ( newChips ) => wrapper.setProps( { chips: newChips } ),
			},
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();
		await nextTick();

		await wrapper.find( '.ext-growthExperiments-interest-selector-related-article-list-item button' ).trigger( 'click' );
		await flushPromises();

		expect( wrapper.emitted( 'update:chips' ) ).toBeTruthy();
		const lastEmit = wrapper.emitted( 'update:chips' ).slice( -1 )[ 0 ][ 0 ];
		expect( lastEmit ).toEqual( [
			{ label: 'seed', value: 'seed' },
			{ label: 'relatedPage', value: 'relatedPage' },
		] );
	} );

	it( 'never fetches related articles when mounted at or above the soft interest limit', async () => {
		const mwApiGet = jest.fn().mockResolvedValue( { query: { pages: {} } } );
		const mwApi = { get: mwApiGet };

		mount( InterestSelector, {
			props: { chips: chipsUpTo( 10 ) },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		expect( mwApiGet ).not.toHaveBeenCalled();
	} );

	it( 'stops fetching new related articles, and prunes newly selected pages from existing ' +
		'suggestions, once the soft interest limit is reached', async () => {
		const mwApiGet = jest.fn().mockResolvedValue( { query: { pages: {
			1: { title: 'suggestedPage' },
		} } } );
		const mwApi = { get: mwApiGet };

		const wrapper = mount( InterestSelector, {
			props: {
				chips: chipsUpTo( 9 ),
				'onUpdate:chips': ( newChips ) => wrapper.setProps( { chips: newChips } ),
			},
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		expect( wrapper.vm.relatedArticles.map( ( article ) => article.value ) ).toContain( 'suggestedPage' );
		const callsBeforeLimit = mwApiGet.mock.calls.length;

		// Selecting the suggested article as the 10th chip crosses the soft limit.
		wrapper.vm.addCardToSelectedChips( { label: 'suggestedPage', value: 'suggestedPage', thumbnail: null } );
		await flushPromises();

		expect( mwApiGet ).toHaveBeenCalledTimes( callsBeforeLimit );
		expect( wrapper.vm.relatedArticles.map( ( article ) => article.value ) ).not.toContain( 'suggestedPage' );
	} );

	it( 'flags a success status once the soft interest limit (10) is reached', () => {
		const mwApi = { get: jest.fn().mockResolvedValue( { query: { pages: {} } } ) };

		const wrapper = mount( InterestSelector, {
			props: { chips: chipsUpTo( 10 ) },
			global: { provide: { mwApi: mwApi } },
		} );

		expect( wrapper.vm.status ).toBe( 'success' );
		expect( wrapper.vm.validationMessages.success ).toBe(
			'growthexperiments-interest-selector-success',
		);
	} );

	it( 'keeps a default status below the soft interest limit', () => {
		const mwApi = { get: jest.fn().mockResolvedValue( { query: { pages: {} } } ) };

		const wrapper = mount( InterestSelector, {
			props: { chips: chipsUpTo( 9 ) },
			global: { provide: { mwApi: mwApi } },
		} );

		expect( wrapper.vm.status ).toBe( 'default' );
	} );

	it( 'shows a no-results message when a search has no matches', async () => {
		const mwApi = makeMwApi( {} );
		const wrapper = mount( InterestSelector, {
			props: { chips: [] },
			global: { provide: { mwApi: mwApi } },
		} );
		await flushPromises();

		wrapper.vm.inputValue = 'no such page';
		wrapper.vm.onUpdateInputValueDebounced( 'no such page' );
		await flushPromises();
		await nextTick();

		expect( wrapper.text() ).toContain( 'growthexperiments-interest-selector-no-results-found' );
	} );
} );
