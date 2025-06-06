'use strict';

jest.mock(
	'./AQSConfig.json',
	() => ( require( '../../tests/qunit/__mocks__/AQSConfig.json' ) ),
	{ virtual: true }
);
jest.mock(
	'./config.json',
	() => ( () => ( require( '../../tests/qunit/__mocks__/config.json' ) ) ),
	{ virtual: true }
);
jest.mock(
	'./TaskTypes.json',
	() => ( require( '../../tests/qunit/__mocks__/TaskTypes.json' ) ),
	{ virtual: true }
);
jest.mock(
	'./DefaultTaskTypes.json',
	() => ( require( '../../tests/qunit/__mocks__/DefaultTaskTypes.json' ) ),
	{ virtual: true }
);
jest.mock(
	'./Topics.json',
	() => ( require( '../../tests/qunit/__mocks__/Topics.json' ) ),
	{ virtual: true }
);

/**
 * Get mock task data
 *
 * @param {string} title
 * @param {string} tasktype
 * @param {number} [pageId]
 * @param {Object} [qualityGateConfig]
 *
 * @return {mw.libs.ge.TaskData}
 */
const getTaskData = ( title, tasktype, pageId, qualityGateConfig ) => ( {
	title,
	tasktype,
	difficulty: 'easy',
	qualityGateIds: [],
	qualityGateConfig: qualityGateConfig || {},
	url: null,
	token: 'token-' + title,
	pageId: pageId || Math.floor( Math.random() * 100 )
} );

/**
 * Mock fetchTasks implementation
 *
 * @param {Array} results
 * @param {number} ms
 *
 * @return {Promise} Native promise with .abort() method to mimic jQuery's
 */
const fetchTaskStub = ( results, ms = 0 ) => {
	const abortP = jest.fn();
	const p = new Promise( ( resolve, reject ) => {
		setTimeout( () => {
			resolve( results );
		}, ms );
		abortP.mockImplementation( ( reason ) => {
			reject( reason );
		} );
	} );
	p.abort = abortP;
	return p;
};

// eslint-disable-next-line no-promise-executor-return
const delay = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );
// HACK, TaskTypesAbFilter is wrapped in an IFFE which makes impossible to mock
// mw.config.get calls from beforeEach hook because the script has already been imported and run
global.mw.config.get = jest.fn();
global.mw.config.get.mockImplementation( ( key ) => {
	switch ( key ) {
		case 'wgGEHomepageModuleActionData-suggested-edits':
			return {
				taskTypes: [
					'copyedit',
					'references',
					'update'
				],
				unavailableTaskTypes: [
					'link-recommendation'
				],
				taskCount: 54,
				topics: []
			};
		default:
			return undefined;
	}
} );
const FiltersStore = require( './FiltersStore.js' );
const NewcomerTasksStore = require( './NewcomerTasksStore.js' );

describe( 'DataStore NewcomerTasksStore', () => {
	it( 'can be newed', () => {
		const filters = new FiltersStore();
		const store = new NewcomerTasksStore( { filters } );
		expect( store ).toBeDefined();
	} );
	// eslint-disable-next-line es-x/no-async-functions
	it( 'unsets the loading state when requests fail', async () => {
		const filters = new FiltersStore();
		const store = new NewcomerTasksStore( { filters } );
		store.fetchExtraDataForTaskIndex = jest.fn().mockReturnValue( Promise.resolve() );
		store.api.fetchTasks = jest.fn()
			.mockReturnValueOnce( fetchTaskStub( {
				tasks: [
					getTaskData( '1', 'copyedit' )
				]
			}, 0 ) )
			.mockRejectedValueOnce( 'Fail' );

		await store.fetchTasks( 'test1' );
		await expect( () => store.fetchTasks( 'test2' ) ).rejects.toEqual( 'Fail' );
		expect( store.isTaskQueueLoading() ).toBe( false );
	} );
	// eslint-disable-next-line es-x/no-async-functions
	it( 'discards last-1 request', async () => {
		const filters = new FiltersStore();
		const store = new NewcomerTasksStore( { filters } );
		store.fetchExtraDataForTaskIndex = jest.fn().mockReturnValue( Promise.resolve() );
		store.api.fetchTasks = jest.fn()
			.mockReturnValueOnce( fetchTaskStub( {
				tasks: [
					getTaskData( '1', 'copyedit' )
				]
			}, 0 ) )
			.mockReturnValueOnce( fetchTaskStub( {
				tasks: [
					getTaskData( '2', 'expand' ),
					getTaskData( '3', 'links' )
				]
			}, 40 ) )
			.mockReturnValueOnce( fetchTaskStub( {
				tasks: [
					getTaskData( '4', 'expand' ),
					getTaskData( '5', 'links' ),
					getTaskData( '6', 'expand' ),
					getTaskData( '7', 'links' )
				]
			}, 0 ) );

		store.fetchTasks( 'test1' );
		store.fetchTasks( 'test2' );
		// Give a chance to the catch callback to run
		await delay( 10 );
		store.fetchTasks( 'test3' );
		// Give a chance to the second request to fullfil if not correctly cancelled, (T369742)
		await delay( 70 );
		expect( store.getTaskCount() ).toBe( 4 );
	} );
} );
