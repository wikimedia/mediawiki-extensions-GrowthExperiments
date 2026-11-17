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
	newcomerTasks: { api: { fetchTasks: mockFetchTasks }, filters: mockFilters },
} ), { virtual: true } );

const { flushPromises } = require( '@vue/test-utils' );

/**
 * @param {number} count
 * @return {Object} An abortable jQuery promise, like GrowthTasksApi.fetchTasks returns
 */
function tasksResponse( count ) {
	return $.Deferred().resolve( { count, tasks: [ { title: 'Dandelion' } ] } )
		.promise( { abort: jest.fn() } );
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
		expect( items ).toEqual( [ { id: 'Dandelion', title: 'Dandelion' } ] );
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
} );
