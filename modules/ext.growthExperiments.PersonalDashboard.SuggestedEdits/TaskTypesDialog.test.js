'use strict';

jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );
// CLiveRegion.vue and useSuggestedEditsCount.js are mapped in from other directories, so they
// do not exist here on disk.
jest.mock( './CLiveRegion.vue', () => require( '../vue-components/CLiveRegion.vue' ), { virtual: true } );
jest.mock(
	'./useSuggestedEditsCount.js',
	() => require( '../ext.growthExperiments.Homepage.SuggestedEdits/useSuggestedEditsCount.js' ),
	{ virtual: true },
);

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

const FINDING_MESSAGE = 'growthexperiments-home-suggestededits-filters-finding';
const COUNT_MESSAGE = 'growthexperiments-home-suggestededits-difficulty-filters-subtitle-count';

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
 * @return {Object} the visible text of the count in the footer
 */
function countText( wrapper ) {
	return wrapper.find( '.cdx-dialog__footer__text .ext-growthExperiments-CLiveRegion__text' );
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

	it( 'shows the count in a live region in the footer, not in the header', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( wrapper.find( '.cdx-dialog__header__subtitle' ).exists() ).toBe( false );
		expect( wrapper.find( '.cdx-dialog__footer__text [role="status"]' ).exists() ).toBe( true );
		expect( countText( wrapper ).text() ).toBe( COUNT_MESSAGE );
	} );

	it( 'shows that it is finding suggested edits, not the old count, when it opens again', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation( () => $.Deferred().promise( { abort: jest.fn() } ) );

		await wrapper.setProps( { open: false } );
		await wrapper.setProps( { open: true } );
		await flushPromises();

		expect( countText( wrapper ).text() ).toBe( FINDING_MESSAGE );
	} );

	it( 'counts again when the selection changes', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( mockApi.fetchTasks ).toHaveBeenLastCalledWith(
			[ 'copyedit', 'expand' ], null, expect.anything(),
		);
	} );

	it( 'shows that it is finding suggested edits while the next count loads', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation( () => $.Deferred().promise( { abort: jest.fn() } ) );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( countText( wrapper ).text() ).toBe( FINDING_MESSAGE );
	} );

	it( 'hides the count when counting fails', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation(
			() => $.Deferred().reject( 'http' ).promise( { abort: jest.fn() } ),
		);

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( countText( wrapper ).element.textContent ).toBe( '\u00a0' );
	} );

	it( 'keeps Done enabled when counting fails', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation(
			() => $.Deferred().reject( 'cirrussearch-too-busy-error' ).promise( { abort: jest.fn() } ),
		);

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( doneButton( wrapper ).attributes() ).not.toHaveProperty( 'disabled' );
	} );

	it( 'blocks Done and shows an error when nothing is selected', async () => {
		const wrapper = await mountOpen( [] );

		expect( doneButton( wrapper ).attributes() ).toHaveProperty( 'disabled' );
		expect( wrapper.find( '.cdx-message--error' ).exists() ).toBe( true );
	} );

	it( 'keeps Done enabled when no task matches', async () => {
		mockApi.fetchTasks.mockImplementation( () => countResponse( 0 ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );

		expect( doneButton( wrapper ).attributes() ).not.toHaveProperty( 'disabled' );
	} );

	it( 'keeps Done enabled while the count loads', async () => {
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation( () => $.Deferred().promise( { abort: jest.fn() } ) );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( countText( wrapper ).text() ).toBe( FINDING_MESSAGE );
		expect( doneButton( wrapper ).attributes() ).not.toHaveProperty( 'disabled' );
	} );

	it( 'ignores a count that arrives for an older selection', async () => {
		const older = $.Deferred();
		mockApi.fetchTasks.mockImplementation( () => older.promise( { abort: jest.fn() } ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation( () => $.Deferred().promise( { abort: jest.fn() } ) );

		await checkbox( wrapper, 'expand' ).setValue( true );
		older.resolve( { count: 5, tasks: [] } );
		await flushPromises();

		expect( countText( wrapper ).text() ).toBe( FINDING_MESSAGE );
	} );

	it( 'ignores an aborted count request', async () => {
		const pending = $.Deferred();
		const abort = jest.fn( () => pending.reject( 'http' ) );
		mockApi.fetchTasks.mockImplementation( () => pending.promise( { abort } ) );
		const wrapper = await mountOpen( [ 'copyedit' ] );
		mockApi.fetchTasks.mockImplementation( () => countResponse( 42 ) );

		await checkbox( wrapper, 'expand' ).setValue( true );
		await flushPromises();

		expect( abort ).toHaveBeenCalled();
		expect( countText( wrapper ).text() ).toBe( COUNT_MESSAGE );
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
