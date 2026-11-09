'use strict';

// ext.personalDashboard.common comes from a sibling extension, so it does not exist here on
// disk. The stand-in for useFeedState gives the loader to the test as load().
jest.mock( 'ext.personalDashboard.common', () => ( {
	useFeedState: ( loader ) => ( { feedState: {}, load: loader } ),
} ), { virtual: true } );

const mockFetchTasks = jest.fn();
jest.mock( 'ext.growthExperiments.DataStore', () => ( {
	newcomerTasks: {
		api: { fetchTasks: mockFetchTasks },
		filters: {
			getTaskTypesQuery: () => [ 'copyedit' ],
			getFiltersQuery: () => null,
		},
	},
} ), { virtual: true } );

const { flushPromises } = require( '@vue/test-utils' );
const { useSuggestedEditsFeed } = require( './useSuggestedEditsFeed.js' );

describe( 'useSuggestedEditsFeed', () => {
	beforeEach( () => {
		mockFetchTasks.mockReset().mockResolvedValue( { tasks: [ { title: 'Dandelion' } ] } );
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
} );
