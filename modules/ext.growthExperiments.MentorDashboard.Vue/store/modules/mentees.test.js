jest.mock( '../../../ext.growthExperiments.MentorDashboard/MenteeOverview/MenteeOverviewApi.js' );
jest.mock( '../Tags.json', () => {
	return {
		questions: []
	};
}, { virtual: true } );

const jsonStoredPresets = ( { usersToShow, maxedits, minedits, onlystarred } = {} ) => {
	return JSON.stringify( {
		usersToShow,
		filters: {
			minedits,
			maxedits,
			onlystarred
		}
	} );
};

describe( 'utils', () => {
	afterEach( () => {
		global.mw.user.options.get = jest.fn();
	} );

	it( 'should return default presets if the stored are JSON invalid and log a warning', () => {
		global.mw.user.options.get = jest.fn( () => 'INVALID' );
		const { createDefaultPresets, getInitialPresetsWithFallback, getters } = require( './mentees.js' );
		const initalPresets = getInitialPresetsWithFallback();
		const defaultPresets = createDefaultPresets();
		expect( initalPresets ).toEqual( defaultPresets );

		const {
			limit,
			editCountMin,
			editCountMax,
			onlyStarred,
			activeDaysAgo
		} = initalPresets;

		const state = {
			all: [],
			isReady: false,
			totalPages: 0,
			page: 0,
			prefix: null,
			limit,
			editCountMin,
			editCountMax,
			onlyStarred,
			activeDaysAgo

		};

		expect( getters.filters( state ) ).toEqual( defaultPresets );
		expect( mw.log.error ).toBeCalledTimes( 0 );
		expect( mw.log.warn ).toBeCalledTimes( 2 );
		expect( mw.log.warn ).toHaveBeenNthCalledWith( 2,
			'Failed parsing JSON stored preference growthexperiments-mentee-overview-presets'
		);
	} );

	it( 'should return stored presets if they are JSON valid and non-stored as undefined', () => {
		const mockPresets = { usersToShow: 15, minedits: 15, onlystarred: true };
		const expectedPresets = {
			limit: 15,
			editCountMax: undefined,
			editCountMin: 15,
			onlyStarred: true,
			activeDaysAgo: undefined
		};
		global.mw.user.options.get = jest.fn( () => jsonStoredPresets( mockPresets ) );
		const { getInitialPresetsWithFallback, getters } = require( './mentees.js' );
		const initalPresets = getInitialPresetsWithFallback();
		expect( initalPresets ).toEqual( expectedPresets );

		const {
			limit,
			editCountMin,
			editCountMax,
			onlyStarred,
			activeDaysAgo
		} = initalPresets;

		const state = {
			all: [],
			isReady: false,
			totalPages: 0,
			page: 0,
			prefix: null,
			limit,
			editCountMin,
			editCountMax,
			onlyStarred,
			activeDaysAgo
		};

		expect( getters.filters( state ) ).toEqual( expectedPresets );
	} );

	it( 'should accept integers or empty string as min and max edit count filters', () => {
		const { validateAndApplyFilters } = require( './mentees.js' );
		const mockContext = {
			getters: { filters: {} },
			commit: jest.fn()
		};
		const res1 = validateAndApplyFilters( mockContext, {
			editCountMin: 'a',
			editCountMax: ''
		} );

		const expectedValidFilters = {
			editCountMax: undefined,
			activeDaysAgo: undefined
		};
		expect( res1 ).toBe( true );
		expect( mockContext.commit ).toHaveBeenNthCalledWith( 1, 'SET_FILTERS', expectedValidFilters );
		expect( mockContext.commit ).toHaveBeenNthCalledWith( 2, 'SET_API_FILTERS', expectedValidFilters );
	} );
} );

// helper for testing action with expected mutations
const testAction = ( action, payload, { state, getters, dispatch }, expectedMutations, done ) => {
	let count = 0;

	// mock commit
	const commit = ( type, commitPayload ) => {
		const mutation = expectedMutations[ count ];

		try {
			expect( type ).toBe( mutation.type );
			expect( commitPayload ).toEqual( mutation.payload );
		} catch ( error ) {
			done( error );
			return;
		}

		count++;
		if ( count >= expectedMutations.length ) {
			done();
		}
	};

	// call the action with mocked store and arguments
	action( { commit, state, getters, dispatch }, payload );

	// check if no mutations should have been dispatched
	if ( expectedMutations.length === 0 ) {
		expect( count ).to.equal( 0 );
		done();
	}
};

describe( 'actions', () => {
	afterEach( () => {
		global.mw.user.options.get = jest.fn();
		jest.clearAllMocks();
	} );

	it( 'should get mentees data and starred mentees with a given limit', ( done ) => {
		const mockPresets = { usersToShow: 15, maxedits: 15, minedits: 15, onlystarred: true };
		global.mw.user.options.get = jest.fn( () => jsonStoredPresets( mockPresets ) );
		const { api, actions } = require( './mentees.js' );

		api.getTotalPages = jest.fn( () => 0 );
		api.getMenteeData = jest.fn( () => $.Deferred().resolve( [] ).promise() );
		api.getStarredMentees = jest.fn( () => $.Deferred().resolve( [] ).promise() );

		const state = {
			all: [],
			isReady: false,
			totalPages: 0,
			page: 0,
			prefix: null,
			limit: 5,
			editCountMin: 0,
			editCountMax: 100,
			onlyStarred: false,
			activeDaysAgo: undefined
		};

		const getters = {
			filters: {
				limit: state.limit,
				editCountMin: state.editCountMin,
				editCountMax: state.editCountMax,
				onlyStarred: state.onlyStarred,
				activeDaysAgo: state.activeDaysAgo
			}
		};

		testAction( actions.getAllMentees, { limit: 10 }, { state, getters }, [
			{ type: 'SET_READY', payload: false },
			{ type: 'SET_LIMIT', payload: 10 },
			{ type: 'SET_FILTERS', payload: {
				limit: 5,
				editCountMin: 0,
				editCountMax: 100,
				onlyStarred: false,
				activeDaysAgo: undefined
			} },
			{ type: 'SET_API_FILTERS', payload: {
				limit: 5,
				editCountMin: 0,
				editCountMax: 100,
				onlyStarred: false,
				activeDaysAgo: undefined
			} },
			{ type: 'SET_PAGES', payload: 0 },
			{ type: 'SET_MENTEES', payload: [] },
			{ type: 'SET_READY', payload: true }
		], done );
	} );
	it( 'should get mentees data and starred mentees with a given filter', ( done ) => {
		const mockPresets = { usersToShow: 15, maxedits: 15, minedits: 15, onlystarred: true };
		global.mw.user.options.get = jest.fn( () => jsonStoredPresets( mockPresets ) );
		const { api, actions } = require( './mentees.js' );

		api.getTotalPages = jest.fn( () => 0 );
		api.getMenteeData = jest.fn( () => $.Deferred().resolve( [] ).promise() );
		api.getStarredMentees = jest.fn( () => $.Deferred().resolve( [] ).promise() );

		const state = {
			all: [],
			isReady: false,
			totalPages: 0,
			page: 0,
			prefix: null,
			limit: 5,
			editCountMin: 0,
			editCountMax: 100,
			onlyStarred: false,
			activeDaysAgo: undefined
		};

		const getters = {
			filters: {
				limit: state.limit,
				editCountMin: state.editCountMin,
				editCountMax: state.editCountMax,
				onlyStarred: state.onlyStarred,
				activeDaysAgo: state.activeDaysAgo
			}
		};

		testAction( actions.getAllMentees, { editCountMin: 1 }, { state, getters }, [
			{ type: 'SET_READY', payload: false },
			{ type: 'SET_FILTERS', payload: {
				limit: 5,
				editCountMin: 1,
				editCountMax: 100,
				onlyStarred: false,
				activeDaysAgo: undefined
			} },
			{ type: 'SET_API_FILTERS', payload: {
				limit: 5,
				editCountMin: 1,
				editCountMax: 100,
				onlyStarred: false,
				activeDaysAgo: undefined
			} },
			{ type: 'SET_PAGES', payload: 0 },
			{ type: 'SET_MENTEES', payload: [] },
			{ type: 'SET_READY', payload: true }
		], done );
	} );
	it( 'should get mentees data and starred mentees with removed filters', ( done ) => {
		const mockPresets = { usersToShow: 15, maxedits: 15, minedits: 15, onlystarred: true };
		global.mw.user.options.get = jest.fn( () => jsonStoredPresets( mockPresets ) );
		const { api, actions } = require( './mentees.js' );

		api.getTotalPages = jest.fn( () => 0 );
		api.getMenteeData = jest.fn( () => $.Deferred().resolve( [] ).promise() );
		api.getStarredMentees = jest.fn( () => $.Deferred().resolve( [] ).promise() );

		const state = {
			all: [],
			isReady: false,
			totalPages: 0,
			page: 0,
			prefix: null,
			limit: 5,
			editCountMin: 0,
			editCountMax: 100,
			onlyStarred: false,
			activeDaysAgo: undefined
		};

		const getters = {
			filters: {
				limit: state.limit,
				editCountMin: state.editCountMin,
				editCountMax: state.editCountMax,
				onlyStarred: state.onlyStarred,
				activeDaysAgo: state.activeDaysAgo
			}
		};

		testAction( actions.getAllMentees, { editCountMin: '' }, { state, getters }, [
			{ type: 'SET_READY', payload: false },
			{ type: 'SET_FILTERS', payload: {
				limit: 5,
				editCountMin: undefined,
				editCountMax: 100,
				onlyStarred: false,
				activeDaysAgo: undefined
			} },
			{ type: 'SET_API_FILTERS', payload: {
				limit: 5,
				editCountMin: undefined,
				editCountMax: 100,
				onlyStarred: false,
				activeDaysAgo: undefined
			} },
			{ type: 'SET_PAGES', payload: 0 },
			{ type: 'SET_MENTEES', payload: [] },
			{ type: 'SET_READY', payload: true }
		], done );
	} );
	it( 'should use filters in store when saving presets', ( done ) => {
		const mockPresets = { usersToShow: 15, maxedits: 15, minedits: 15, onlystarred: true };
		global.mw.user.options.get = jest.fn( () => jsonStoredPresets( mockPresets ) );
		const { api, actions, MENTEE_OVERVIEW_PRESETS_PREF } = require( './mentees.js' );

		api.getTotalPages = jest.fn( () => 0 );
		api.getMenteeData = jest.fn( () => $.Deferred().resolve( [] ).promise() );
		api.getStarredMentees = jest.fn( () => $.Deferred().resolve( [] ).promise() );

		const getters = {
			filters: {
				limit: 15,
				editCountMin: 1,
				editCountMax: 100,
				onlyStarred: true,
				activeDaysAgo: 60
			}
		};

		const dispatch = jest.fn( () => $.Deferred().resolve().promise() );

		actions.savePresets( { getters, dispatch } )
			.then( () => {
				expect( dispatch ).toHaveBeenNthCalledWith( 1,
					'userPreferences/saveOption',
					{
						name: MENTEE_OVERVIEW_PRESETS_PREF,
						value: {
							usersToShow: 15,
							filters: {
								minedits: 1,
								maxedits: 100,
								onlystarred: true,
								activedaysago: 60
							}
						}
					},
					{
						root: true
					}
				);
				done();
			} )
			.catch( done );
	} );
} );
