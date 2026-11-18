'use strict';

// ext.personalDashboard.common comes from a sibling extension, so it does not exist here on
// disk. The stand-in for useFeedState gives the loader to the test as load().
jest.mock( 'ext.personalDashboard.common', () => ( {
	useFeedState: ( loader ) => ( { feedState: {}, load: loader } ),
} ), { virtual: true } );

const mockFetchTasks = jest.fn();
const mockFilters = {
	getTaskTypesQuery: jest.fn(),
	getFiltersQuery: jest.fn( () => null ),
	getSelectedInterests: jest.fn(),
};
jest.mock( 'ext.growthExperiments.DataStore', () => ( {
	CONSTANTS: { AVAILABLE_TASK_TYPE_IDS: [ 'copyedit', 'expand' ] },
	newcomerTasks: { api: { fetchTasks: mockFetchTasks }, filters: mockFilters },
} ), { virtual: true } );

const { flushPromises } = require( '@vue/test-utils' );

/**
 * @param {number} count
 * @param {string[]} [titles] Titles of the tasks
 * @return {Object} An abortable jQuery promise, like GrowthTasksApi.fetchTasks returns
 */
function tasksResponse( count, titles = [ 'Dandelion' ] ) {
	return $.Deferred().resolve( { count, tasks: titles.map( ( title ) => ( { title } ) ) } )
		.promise( { abort: jest.fn() } );
}

/**
 * @param {Object} interestFilters The filters argument of a fetchTasks call
 * @return {string[]|null} The interests that the request sends, or null for none
 */
function sentInterests( interestFilters ) {
	return interestFilters && interestFilters.hasSelection() ?
		interestFilters.getInterests() :
		null;
}

describe( 'useSuggestedEditsFeed', () => {
	let useSuggestedEditsFeed;

	beforeEach( () => {
		jest.clearAllMocks();
		mockFilters.getTaskTypesQuery.mockReturnValue( [ 'copyedit' ] );
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Botany' ] );
		mockFetchTasks.mockReset().mockImplementation( () => tasksResponse( 12 ) );
		// The count is module state. Start each test without one.
		jest.isolateModules( () => {
			( { useSuggestedEditsFeed } = require( './useSuggestedEditsFeed.js' ) );
		} );
	} );

	it( 'asks for the tasks immediately when there is nothing to wait for', async () => {
		const { load } = useSuggestedEditsFeed();

		const items = await load( 20 );

		expect( mockFetchTasks ).toHaveBeenCalledWith(
			[ 'copyedit' ], null, expect.objectContaining( { size: 20 } ),
		);
		expect( items ).toEqual( [ { id: 'Dandelion', title: 'Dandelion', isFallback: false } ] );
	} );

	it( 'waits for the given promise before it asks for the tasks', async () => {
		const { load } = useSuggestedEditsFeed();
		const saving = $.Deferred();

		const loading = load( 20, saving.promise() );
		await flushPromises();
		expect( mockFetchTasks ).not.toHaveBeenCalled();

		saving.resolve();
		await loading;
		expect( mockFetchTasks ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'the count of the stored selection', () => {
		it( 'is not known before the first load', () => {
			expect( useSuggestedEditsFeed().getSavedCount() ).toBeNull();
		} );

		it( 'comes from the last load', async () => {
			const { load, getSavedCount } = useSuggestedEditsFeed();

			await load( 10 );

			await expect( getSavedCount() ).resolves.toEqual( { count: 12 } );
		} );

		it( 'is available while the load continues', async () => {
			const pending = $.Deferred();
			mockFetchTasks.mockReturnValue( pending.promise( { abort: jest.fn() } ) );
			const { load, getSavedCount } = useSuggestedEditsFeed();

			load( 10 );
			await flushPromises();
			const count = getSavedCount();
			pending.resolve( { count: 4, tasks: [] } );

			await expect( count ).resolves.toEqual( { count: 4 } );
		} );

		it( 'does not let its user abort the request of the feed', async () => {
			const { load, getSavedCount } = useSuggestedEditsFeed();

			await load( 10 );

			expect( getSavedCount().abort ).toBeUndefined();
		} );

		it.each( [
			[ 'task types', () => mockFilters.getTaskTypesQuery.mockReturnValue( [ 'expand' ] ) ],
			[ 'interests', () => mockFilters.getSelectedInterests.mockReturnValue( [ 'Fungi' ] ) ],
		] )( 'is not known when the stored %s change', async ( name, change ) => {
			const { load, getSavedCount } = useSuggestedEditsFeed();
			await load( 10 );

			change();

			expect( getSavedCount() ).toBeNull();
		} );

		it( 'is not known when the load fails', async () => {
			mockFetchTasks.mockReturnValue(
				$.Deferred().reject( 'http' ).promise( { abort: jest.fn() } ),
			);
			const { load, getSavedCount } = useSuggestedEditsFeed();

			await expect( load( 10 ) ).rejects.toThrow( 'http' );
			await flushPromises();

			expect( getSavedCount() ).toBeNull();
		} );
	} );

	describe( 'the fallback items', () => {
		beforeEach( () => {
			mockFetchTasks
				.mockImplementationOnce( () => tasksResponse( 0, [] ) )
				.mockImplementation( () => tasksResponse( 30, [ 'Tulip' ] ) );
		} );

		it( 'are not requested when tasks match the selection', async () => {
			mockFetchTasks.mockReset().mockImplementation( () => tasksResponse( 12 ) );
			const { load } = useSuggestedEditsFeed();

			await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'ignore the interests and keep the task types when no tasks match', async () => {
			const { load } = useSuggestedEditsFeed();

			const items = await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 2 );
			const [ taskTypes, interestFilters, config ] = mockFetchTasks.mock.calls[ 1 ];
			expect( taskTypes ).toEqual( [ 'copyedit' ] );
			expect( sentInterests( interestFilters ) ).toEqual( [] );
			expect( config ).toEqual( expect.objectContaining( {
				size: 10,
				context: 'personaldashboard_suggestededits_fallback',
			} ) );
			expect( items ).toEqual( [
				{ id: 'Tulip', title: 'Tulip', isFallback: true, fallbackTier: 'selected_task_types' },
			] );
		} );

		it( 'ask for all available task types when no tasks match the selected task types', async () => {
			mockFetchTasks.mockReset()
				.mockImplementationOnce( () => tasksResponse( 0, [] ) )
				.mockImplementationOnce( () => tasksResponse( 0, [] ) )
				.mockImplementation( () => tasksResponse( 30, [ 'Tulip' ] ) );
			const { load } = useSuggestedEditsFeed();

			const items = await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 3 );
			const [ taskTypes, interestFilters, config ] = mockFetchTasks.mock.calls[ 2 ];
			expect( taskTypes ).toEqual( [ 'copyedit', 'expand' ] );
			expect( sentInterests( interestFilters ) ).toEqual( [] );
			expect( config ).toEqual( expect.objectContaining( {
				context: 'personaldashboard_suggestededits_fallback_all',
			} ) );
			expect( items ).toEqual( [
				{ id: 'Tulip', title: 'Tulip', isFallback: true, fallbackTier: 'all_task_types' },
			] );
		} );

		it( 'ask only for all available task types when the user has no interests', async () => {
			mockFilters.getSelectedInterests.mockReturnValue( [] );
			const { load } = useSuggestedEditsFeed();

			await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 2 );
			expect( mockFetchTasks.mock.calls[ 1 ][ 0 ] ).toEqual( [ 'copyedit', 'expand' ] );
		} );

		it( 'ask only once when the user selected all available task types', async () => {
			mockFilters.getTaskTypesQuery.mockReturnValue( [ 'copyedit', 'expand' ] );
			mockFetchTasks.mockReset().mockImplementation( () => tasksResponse( 0, [] ) );
			const { load } = useSuggestedEditsFeed();

			const items = await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 2 );
			expect( items ).toEqual( [] );
		} );

		it( 'are empty when no tasks match any task type', async () => {
			mockFetchTasks.mockReset().mockImplementation( () => tasksResponse( 0, [] ) );
			const { load } = useSuggestedEditsFeed();

			const items = await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 3 );
			expect( items ).toEqual( [] );
		} );

		it( 'are the same in the next load with the same task types', async () => {
			const { load } = useSuggestedEditsFeed();
			const first = await load( 10 );
			mockFetchTasks.mockImplementationOnce( () => tasksResponse( 0, [] ) );
			mockFilters.getSelectedInterests.mockReturnValue( [ 'Fungi' ] );

			const second = await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 3 );
			expect( second ).toEqual( first );
		} );

		it( 'get the tier of the request that gives them, also when they are stored', async () => {
			mockFilters.getTaskTypesQuery.mockReturnValue( [ 'copyedit', 'expand' ] );
			const { load } = useSuggestedEditsFeed();
			const first = await load( 10 );
			expect( first[ 0 ].fallbackTier ).toBe( 'selected_task_types' );
			// Without interests, only the request for all the task types is sent. It has the
			// same key as the request for the selected task types above.
			mockFetchTasks.mockImplementationOnce( () => tasksResponse( 0, [] ) );
			mockFilters.getSelectedInterests.mockReturnValue( [] );

			const second = await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 3 );
			expect( second[ 0 ].fallbackTier ).toBe( 'all_task_types' );
		} );

		it( 'are requested again for other task types', async () => {
			const { load } = useSuggestedEditsFeed();
			await load( 10 );
			mockFetchTasks.mockImplementationOnce( () => tasksResponse( 0, [] ) );
			mockFilters.getTaskTypesQuery.mockReturnValue( [ 'expand' ] );

			await load( 10 );

			expect( mockFetchTasks ).toHaveBeenCalledTimes( 4 );
			expect( mockFetchTasks.mock.calls[ 3 ][ 0 ] ).toEqual( [ 'expand' ] );
		} );

		it( 'do not change the count of the stored selection', async () => {
			const { load, getSavedCount } = useSuggestedEditsFeed();

			await load( 10 );

			await expect( getSavedCount() ).resolves.toEqual( { count: 0 } );
		} );

		it( 'keep the last items when the fallback request is aborted', async () => {
			mockFetchTasks.mockReset()
				.mockImplementationOnce( () => tasksResponse( 12 ) )
				.mockImplementationOnce( () => tasksResponse( 0, [] ) )
				.mockImplementationOnce( () => $.Deferred().reject( 'abort' ).promise( { abort: jest.fn() } ) );
			const { load } = useSuggestedEditsFeed();
			const first = await load( 10 );

			const second = await load( 10 );

			expect( second ).toBe( first );
		} );

		it( 'fail the load when the fallback request fails', async () => {
			mockFetchTasks.mockReset()
				.mockImplementationOnce( () => tasksResponse( 0, [] ) )
				.mockImplementationOnce( () => $.Deferred().reject( 'http' ).promise( { abort: jest.fn() } ) );
			const { load } = useSuggestedEditsFeed();

			await expect( load( 10 ) ).rejects.toThrow( 'http' );
		} );
	} );
} );
