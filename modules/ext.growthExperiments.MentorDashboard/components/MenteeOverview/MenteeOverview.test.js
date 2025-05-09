jest.mock( '../../../vue-components/icons.json', () => ( {
	cdxIconError: ''
} ), { virtual: true } );
const { shallowMount } = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const MenteeOverview = require( './MenteeOverview.vue' );
const MenteeFilters = require( './MenteeFilters.vue' );
const NoResults = require( './NoResults.vue' );
const DataTable = require( '../DataTable/DataTable.vue' );
const menteesJSON = require( '../../../../tests/qunit/__mocks__/mentees.json' );

describe( 'MenteeOverview', () => {
	let menteeActions, userPreferencesActions, userPreferencesGetters, menteeGetters;
	let store;

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
		store = new Vuex.Store( {
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
		// Use a well-known window name to have the component avoid passing an anchor to CdxPopover.
		// CdxPopover needs to be shallow rendered for reasons outlined above, and vue-test-utils
		// is unable to stringify references holding an HTML element.
		global.window.name = 'MenteeOverviewJestTests';
	} );
	it( 'it dispatches "mentees/getAllMentees" when mounted', () => {
		shallowMount( MenteeOverview, {
			global: {
				mocks: {
					$store: store
				}
			}
		} );
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 1 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 1, expect.any( Object ), {
			limit: 10,
			editCountMin: 0,
			editCountMax: 500,
			onlyStarred: false
		} );
	} );

	it( 'it dispatches "mentees/getAllMentees" when "DataTable" navigates a page', () => {
		const wrapper = shallowMount( MenteeOverview, {
			global: {
				mocks: {
					$store: store
				}
			}
		} );
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
		const wrapper = shallowMount( MenteeOverview, {
			global: {
				mocks: {
					$store: store
				}
			}
		} );

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
		const wrapper = shallowMount( MenteeOverview, {
			global: {
				mocks: {
					$store: store
				}
			}
		} );

		wrapper.findComponent( DataTable ).vm.$emit( 'update:sorting', { sortBy: 'questions', order: 'asc' } );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.updateSorting
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 2 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), { sortBy: 'questions', order: 'asc' }
		);
	} );
	it( 'it dispatches "mentees/getAllMentees" when "MenteeFilters" updates the filters and saves them', () => {
		const wrapper = shallowMount( MenteeOverview, {
			global: {
				mocks: {
					$store: store
				}
			}
		} );

		const update = {
			editCountMin: 10,
			editCountMax: 100,
			onlyStarred: true,
			page: 1
		};
		wrapper.findComponent( MenteeFilters ).vm.$emit( 'update:filters', update );
		// 1st time in MenteeOverview.created
		// 2nd time in MenteeOverview.updateFilters
		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 2 );
		expect( menteeActions.getAllMentees ).toHaveBeenNthCalledWith( 2,
			expect.any( Object ), update
		);
		expect( menteeActions.savePresets ).toHaveBeenCalledTimes( 1 );
	} );
	it( 'it displays the no results view when the API returns 0 mentees', () => {
		menteeGetters.allMentees = jest.fn( () => ( [] ) );
		const storeWithoutMentees = new Vuex.Store( {
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
		const wrapper = shallowMount( MenteeOverview, {
			global: {
				mocks: {
					$store: storeWithoutMentees
				}
			}
		} );

		expect( menteeActions.getAllMentees ).toHaveBeenCalledTimes( 1 );
		const noResults = wrapper.findComponent( NoResults );

		expect( noResults.attributes( 'text' ) )
			.toContain( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-headline' );
		expect( noResults.attributes( 'description' ) )
			.toContain( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-text' );
	} );
} );
