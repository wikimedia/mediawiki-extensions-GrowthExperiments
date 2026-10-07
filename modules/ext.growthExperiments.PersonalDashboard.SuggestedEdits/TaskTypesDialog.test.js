'use strict';

jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );

const mockApi = { fetchTasks: jest.fn() };
const mockFilters = {
	getSelectedTaskTypes: jest.fn(),
	getFiltersQuery: jest.fn( () => null ),
	setSelectedTaskTypes: jest.fn(),
	savePreferences: jest.fn(),
};
jest.mock( 'ext.growthExperiments.DataStore', () => ( {
	CONSTANTS: {
		ALL_TASK_TYPES: {
			copyedit: {
				id: 'copyedit',
				difficulty: 'easy',
				messages: { label: 'Copyedit' },
				iconData: {},
			},
			'link-recommendation': {
				id: 'link-recommendation',
				difficulty: 'easy',
				messages: { label: 'Add links' },
				iconData: {
					filterIcon: 'robot',
					descriptionMessageKey: 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				},
				disabled: true,
				unavailable: true,
				extraMessages: { unavailable: 'You have done enough of these.' },
			},
			'revise-tone': {
				id: 'revise-tone',
				difficulty: 'easy',
				messages: { label: 'Revise tone' },
				iconData: {
					filterIcon: 'robot',
					descriptionMessageKey: 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				},
			},
			expand: {
				id: 'expand',
				difficulty: 'hard',
				messages: { label: 'Expand' },
				iconData: {},
			},
		},
	},
	newcomerTasks: { api: mockApi, filters: mockFilters },
} ), { virtual: true } );

const { mount, flushPromises } = require( '@vue/test-utils' );
const TaskTypesDialog = require( './TaskTypesDialog.vue' );

/**
 * @param {number} count Value the count request resolves with
 * @return {Object} An abortable jQuery promise, like GrowthTasksApi.fetchTasks returns
 */
function countResponse( count ) {
	return $.Deferred().resolve( { count, tasks: [] } ).promise( { abort: jest.fn() } );
}

/**
 * @param {string[]} storedTaskTypes
 * @return {Promise<Object>} the mounted wrapper, with the first count request settled
 */
async function mountOpen( storedTaskTypes ) {
	mockFilters.getSelectedTaskTypes.mockReturnValue( storedTaskTypes );
	const wrapper = mount( TaskTypesDialog, {
		props: { open: true },
		global: { stubs: { teleport: true } },
	} );
	await flushPromises();
	return wrapper;
}

/**
 * @param {Object} wrapper
 * @param {string} id
 * @return {Object} the checkbox input for the given task type
 */
function checkbox( wrapper, id ) {
	return wrapper.find( 'input[value="' + id + '"]' );
}

/**
 * @param {Object} wrapper
 * @return {Object} the primary (Done) button
 */
function doneButton( wrapper ) {
	return wrapper.find( '.cdx-dialog__footer__primary-action' );
}

describe( 'TaskTypesDialog', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockApi.fetchTasks.mockImplementation( () => countResponse( 42 ) );
	} );

	it( 'groups task types by difficulty and leaves out empty groups', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		const legends = wrapper.findAll( 'legend' ).map( ( legend ) => legend.text() );
		expect( legends ).toEqual( [
			'growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label',
			'growthexperiments-homepage-startediting-dialog-difficulty-level-hard-label',
		] );
	} );

	it( 'disables an unavailable task type and explains why', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( checkbox( wrapper, 'link-recommendation' ).attributes() ).toHaveProperty( 'disabled' );
		expect( wrapper.text() ).toContain( 'You have done enough of these.' );
	} );

	it( 'labels machine suggestions', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( wrapper.text() ).toContain( 'growthexperiments-homepage-suggestededits-tasktype-machine-description' );
	} );

	it( 'starts from the stored selection', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( checkbox( wrapper, 'copyedit' ).element.checked ).toBe( true );
		expect( checkbox( wrapper, 'expand' ).element.checked ).toBe( false );
		expect( mockApi.fetchTasks ).toHaveBeenLastCalledWith(
			[ 'copyedit' ], null, expect.anything(),
		);
	} );

	it( 'counts again when the selection changes', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( mockApi.fetchTasks ).toHaveBeenLastCalledWith(
			[ 'copyedit', 'expand' ], null, expect.anything(),
		);
	} );

	it( 'keeps the last count while the next one loads', async () => {
		const originalMessage = mw.message.getMockImplementation();
		mw.message.mockImplementation( ( key, ...params ) => ( { text: () => [ key, ...params ].join( ' ' ) } ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation( () => $.Deferred().promise( { abort: jest.fn() } ) );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( wrapper.find( '.cdx-dialog__header__subtitle' ).text() ).toBe(
			'growthexperiments-homepage-suggestededits-difficulty-filters-subtitle-count 42',
		);
		mw.message.mockImplementation( originalMessage );
	} );

	it( 'hides the count when counting fails', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation(
			() => $.Deferred().reject( 'http' ).promise( { abort: jest.fn() } ),
		);

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( wrapper.find( '.cdx-dialog__header__subtitle' ).exists() ).toBe( false );
	} );

	it( 'blocks Done and shows an error when nothing is selected', async () => {
		const wrapper = await mountOpen( [] );

		expect( doneButton( wrapper ).attributes() ).toHaveProperty( 'disabled' );
		expect( wrapper.find( '.cdx-message--error' ).exists() ).toBe( true );
	} );

	it( 'blocks Done when no task matches', async () => {
		mockApi.fetchTasks.mockImplementation( () => countResponse( 0 ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( doneButton( wrapper ).attributes() ).toHaveProperty( 'disabled' );
	} );

	it( 'blocks Done until the count for a new selection arrives', async () => {
		mockApi.fetchTasks.mockImplementation( () => countResponse( 0 ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );
		const pending = $.Deferred();
		mockApi.fetchTasks.mockImplementation( () => pending.promise( { abort: jest.fn() } ) );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();
		expect( doneButton( wrapper ).attributes() ).toHaveProperty( 'disabled' );

		pending.resolve( { count: 0, tasks: [] } );
		await flushPromises();
		expect( doneButton( wrapper ).attributes() ).toHaveProperty( 'disabled' );
	} );

	it( 'ignores a count that arrives for an older selection', async () => {
		let runDebounced;
		mw.util.debounce.mockImplementationOnce( ( fn ) => {
			runDebounced = fn;
			return () => {};
		} );
		const older = $.Deferred();
		mockApi.fetchTasks.mockImplementation( () => older.promise( { abort: jest.fn() } ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );
		runDebounced();

		await checkbox( wrapper, 'expand' ).setValue( true );
		older.resolve( { count: 5, tasks: [] } );
		await flushPromises();

		expect( doneButton( wrapper ).attributes() ).toHaveProperty( 'disabled' );
	} );

	it( 'ignores an aborted count request', async () => {
		mockApi.fetchTasks.mockImplementation(
			() => $.Deferred().reject( 'abort' ).promise( { abort: jest.fn() } ),
		);
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( doneButton( wrapper ).attributes() ).not.toHaveProperty( 'disabled' );
	} );

	it( 'stores the selection on Done and reports it', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();
		await doneButton( wrapper ).trigger( 'click' );

		expect( mockFilters.setSelectedTaskTypes ).toHaveBeenCalledWith( [ 'copyedit', 'expand' ] );
		expect( mockFilters.savePreferences ).toHaveBeenCalled();
		expect( wrapper.emitted( 'saved' ) ).toHaveLength( 1 );
		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'leaves the store alone on Cancel', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await wrapper.find( '.cdx-dialog__footer__default-action' ).trigger( 'click' );

		expect( mockFilters.setSelectedTaskTypes ).not.toHaveBeenCalled();
		expect( mockFilters.savePreferences ).not.toHaveBeenCalled();
		expect( wrapper.emitted( 'saved' ) ).toBeUndefined();
		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );
} );
