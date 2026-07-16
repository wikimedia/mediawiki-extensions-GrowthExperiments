jest.mock( '../../../vue-components/icons.json', () => ( {
	cdxIconError: ''
} ), { virtual: true } );
const { shallowMount } = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const MenteeOverview = require( './MenteeOverview.vue' );
const MenteeFilters = require( './MenteeFilters.vue' );
const MenteeSearch = require( './MenteeSearch.vue' );
const NoResults = require( './NoResults.vue' );
const DataTable = require( '../DataTable/DataTable.vue' );
const menteesJSON = require( '../../../../tests/qunit/__mocks__/mentees.json' );

describe( 'MenteeOverview', () => {
	let menteeActions, userPreferencesActions, userPreferencesGetters, menteeGetters;
	let store;

	// Build a store from the current mock closures. Getters must be finalised before
	// calling this: Vuex captures the getter references at construction, so a test that
	// overrides a getter has to rebuild rather than mutate an already-mounted store.
	const buildStore = () => new Vuex.Store( {
		modules: {
			mentees: {
				actions: menteeActions,
				getters: menteeGetters,
				namespaced: true
			},
			userPreferences: {
				actions: userPreferencesActions,
				getters: userPreferencesGetters,
				namespaced: true
			}
		}
	} );

	const mountOverview = ( storeInstance = store ) => shallowMount( MenteeOverview, {
		global: {
			mocks: {
				$store: storeInstance
			}
		}
	} );

	beforeEach( () => {
		menteeActions = {
			getAllMentees: jest.fn( () => $.Deferred().resolve().promise() ),
			savePresets: jest.fn( () => $.Deferred().resolve().promise() )
		};
		userPreferencesGetters = {
			getPreferenceByName: jest.fn( () => ( x ) => x )
		};
		userPreferencesActions = {
			saveOption: jest.fn( () => $.Deferred().resolve().promise() )
		};
		menteeGetters = {
			isReady: jest.fn( () => true ),
			currentPage: jest.fn( () => 1 ),
			allMentees: jest.fn( () => menteesJSON.mentees ),
			filters: jest.fn( () => ( {
				limit: 10,
				editCountMin: 0,
				editCountMax: 500,
				onlyStarred: false
			} ) )
		};
		store = buildStore();
		// Use a well-known window name to have the component avoid passing an anchor to CdxPopover.
		// CdxPopover needs to be shallow rendered for reasons outlined above, and vue-test-utils
		// is unable to stringify references holding an HTML element.
		global.window.name = 'MenteeOverviewJestTests';
	} );
	it( 'it dispatches "mentees/getAllMentees" when mounted', () => {
		mountOverview();
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 1 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 1, expect.any( Object ), {
			limit: 10,
			editCountMin: 0,
			editCountMax: 500,
			onlyStarred: false
		} );
	} );

	it( 'it dispatches "mentees/getAllMentees" when "DataTable" navigates a page', () => {
		const wrapper = mountOverview();
		wrapper.findComponent( DataTable ).vm.$emit( 'update:next-page' );
		wrapper.findComponent( DataTable ).vm.$emit( 'update:prev-page' );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.navigateToNextPage
		// 3rd time in MenteeOverview.navigateToPrevPage
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 3 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), { page: 2 }
		);
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 3,
			expect.any( Object ), { page: 0 }
		);
	} );
	it( 'it dispatches "mentees/getAllMentees" when "DataTable" updates the limit and saves it', () => {
		const wrapper = mountOverview();

		wrapper.findComponent( DataTable ).vm.$emit( 'update:limit', 15 );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.updateLimit
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 2 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), { limit: 15, page: 1 }
		);
		expect( menteeActions.savePresets ).toHaveBeenCalledTimes( 1 );
	} );
	it( 'it dispatches "mentees/getAllMentees" when "DataTable" updates the sorting', () => {
		const wrapper = mountOverview();

		wrapper.findComponent( DataTable ).vm.$emit( 'update:sorting', { sortBy: 'questions', order: 'asc' } );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.updateSorting
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 2 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), { sortBy: 'questions', order: 'asc' }
		);
	} );
	it( 'it dispatches "mentees/getAllMentees" resetting the page when "MenteeFilters" updates the filters and saves them', () => {
		// Start on page 2 and emit filters without a page: this proves the handler
		// forces page 1 (resetting) rather than echoing whatever page the caller was
		// on, guarding the same class of regression as T432190.
		menteeGetters.currentPage = jest.fn( () => 2 );
		const wrapper = mountOverview( buildStore() );

		const update = {
			editCountMin: 10,
			editCountMax: 100,
			onlyStarred: true
		};
		wrapper.findComponent( MenteeFilters ).vm.$emit( 'update:filters', update );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.updateFilters
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 2 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), Object.assign( {}, update, { page: 1 } )
		);
		expect( menteeActions.savePresets ).toHaveBeenCalledTimes( 1 );
	} );
	it( 'it dispatches "mentees/getAllMentees" resetting the page when "MenteeSearch" selects a username', () => {
		// Put the component on page 2, which is where the bug reproduced: a username
		// match that lives on page 1 falls outside the page-2 offset window and the
		// dashboard shows "No mentees found" (T432190). Selecting a suggestion must
		// reset the page so the match is visible again.
		menteeGetters.currentPage = jest.fn( () => 2 );
		const wrapper = mountOverview( buildStore() );

		wrapper.findComponent( MenteeSearch ).vm.$emit( 'update:selected', 'Laura' );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.onMenteeSearchSelection
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 2 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), { prefix: 'Laura', page: 1 }
		);
	} );
	it( 'it displays the no results view when the API returns 0 mentees', () => {
		menteeGetters.allMentees = jest.fn( () => ( [] ) );
		const wrapper = mountOverview( buildStore() );

		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 1 );
		const noResults = wrapper.findComponent( NoResults );

		expect( noResults.attributes( 'text' ) )
			.toContain( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-headline' );
		expect( noResults.attributes( 'description' ) )
			.toContain( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-text' );
	} );
} );
