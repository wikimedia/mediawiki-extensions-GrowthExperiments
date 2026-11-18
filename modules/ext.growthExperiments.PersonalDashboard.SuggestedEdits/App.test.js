'use strict';

/*
 * CodexModule generates codex.js and icons.json at ResourceLoader time,
 * InterestSelectorDialog.vue is mapped in from another directory, and
 * ext.personalDashboard.common comes from a sibling extension, so none of them
 * exist here on disk for jest to resolve. The panels and the feed are stubbed:
 * these tests cover the interests notice, the welcome back dialog, the menu
 * icon and the instrumentation.
 */
jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );
jest.mock( './icons.json', () => ( {
	cdxIconConfigure: '<path d="M2"/>',
	cdxIconLevelNone: 'level-none',
	cdxIconLevelOne: 'level-one',
	cdxIconLevelTwo: 'level-two',
	cdxIconLevelThree: 'level-three',
} ), { virtual: true } );
jest.mock( './AboutPanel.vue', () => ( { render: () => null } ) );
jest.mock( './TaskCard.vue', () => ( {
	name: 'TaskCard',
	props: [ 'task', 'position' ],
	render: () => require( 'vue' ).h( 'div' ),
} ) );
jest.mock( './TaskTypesDialog.vue', () => ( {
	name: 'TaskTypesDialog',
	props: [ 'open', 'getSavedCount' ],
	emits: [ 'update:open', 'saved' ],
	render: () => null,
} ) );
jest.mock( './InterestSelectorDialog.vue', () => ( {
	name: 'InterestSelectorDialog',
	props: { open: Boolean, stackedActions: Boolean, getSavedCount: Function },
	emits: [ 'update:open', 'saved' ],
	render: () => null,
} ), { virtual: true } );
jest.mock( './WelcomeBackDialog.vue', () => ( {
	name: 'WelcomeBackDialog',
	props: [ 'open' ],
	emits: [ 'update:open', 'select-interests' ],
	render: () => null,
} ) );

const mockLoad = jest.fn( () => Promise.resolve() );
const mockMenuFocus = jest.fn();
const mockFeedState = require( 'vue' ).reactive( { items: [], isLoading: true, error: null } );
const mockGetSavedCount = jest.fn();
jest.mock( './useSuggestedEditsFeed.js', () => ( {
	useSuggestedEditsFeed: () => ( {
		feedState: mockFeedState,
		load: mockLoad,
		getSavedCount: mockGetSavedCount,
	} ),
} ) );
jest.mock( 'ext.personalDashboard.common', () => ( {
	FULL_LIMIT: 20,
	FeedCard: { render: () => null },
	FeedPanel: {
		name: 'FeedPanel',
		props: [ 'items', 'emptyLabel' ],
		render() {
			return require( 'vue' ).h(
				'div',
				this.items.map( ( item ) => this.$slots.item( { item } ) ),
			);
		},
	},
	ModuleHeaderMenu: {
		name: 'ModuleHeaderMenu',
		props: [ 'menuItems', 'footerItem', 'buttonLabel' ],
		emits: [ 'select', 'button-click', 'menu-open' ],
		methods: { focus: mockMenuFocus },
		render() {
			return this.$slots.default( { anchor: null } );
		},
	},
} ), { virtual: true } );

const mockFilters = {
	getSelectedInterests: jest.fn(),
	getSelectedTaskTypes: jest.fn( () => [] ),
};
jest.mock( 'ext.growthExperiments.DataStore', () => ( {
	CONSTANTS: {
		ALL_TASK_TYPES: {
			copyedit: { difficulty: 'easy' },
			links: { difficulty: 'easy' },
			references: { difficulty: 'medium' },
			update: { difficulty: 'medium' },
			expand: { difficulty: 'hard' },
		},
	},
	newcomerTasks: { filters: mockFilters },
} ), { virtual: true } );

const { mount, flushPromises } = require( '@vue/test-utils' );
const App = require( './App.vue' );

const mockSaveOption = jest.fn();
global.mw.storage = { session: { get: jest.fn( () => null ), set: jest.fn() } };
const mockInstrumentation = {
	recordModuleShownAfterVisibleOnce: jest.fn(),
	sendClick: jest.fn(),
	sendModuleReady: jest.fn(),
};

/**
 * @param {string|null} action Value of wgGEHomeOnboardingAction
 * @param {Object} [options] More mount options
 * @return {Object} the mounted wrapper
 */
function mountApp( action, options ) {
	mw.config.get.mockImplementation(
		( key ) => ( key === 'wgGEHomeOnboardingAction' ? action : undefined ),
	);
	return mount( App, {
		props: { headerTarget: '#header' },
		global: {
			provide: { HOME_MODULE_INSTRUMENTATION: mockInstrumentation },
			stubs: { teleport: true, CdxPopover: true },
		},
		...options,
	} );
}

/**
 * @param {Object} wrapper
 * @return {Object} the wrapper of the stubbed interest selector dialog
 */
function interestSelector( wrapper ) {
	return wrapper.findComponent( { name: 'InterestSelectorDialog' } );
}

describe( 'App interests notice', () => {
	beforeEach( () => {
		mockLoad.mockClear();
		mockSaveOption.mockReset().mockResolvedValue( {} );
		mockFilters.getSelectedInterests.mockReturnValue( [] );
		global.mw.Api = jest.fn( () => ( { saveOption: mockSaveOption } ) );
		global.mw.errorLogger = { logError: jest.fn() };
	} );

	it.each( [
		'show-no-interests-notice',
		'show-welcome-back',
	] )( 'shows the notice for the "%s" action', ( action ) => {
		const wrapper = mountApp( action );

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( true );
	} );

	it.each( [
		null,
		undefined,
	] )( 'does not show the notice for the %s action', ( action ) => {
		const wrapper = mountApp( action );

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
	} );

	it( 'opens the interest selector from the notice', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );
		expect( interestSelector( wrapper ).props( 'open' ) ).toBe( false );

		await wrapper.find( '.growthexperiments-interests-notice__button' ).trigger( 'click' );

		expect( interestSelector( wrapper ).props( 'open' ) ).toBe( true );
	} );

	it( 'hides the notice and reloads the feed when interests are saved', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );
		mockLoad.mockClear();
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

		interestSelector( wrapper ).vm.$emit( 'saved', Promise.resolve() );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockLoad ).toHaveBeenCalledWith( 20, expect.any( Promise ) );
		expect( mockSaveOption ).not.toHaveBeenCalled();
	} );

	it( 'reloads the feed immediately, and gives it the save to wait for', () => {
		const wrapper = mountApp( null );
		mockLoad.mockClear();
		// The save does not end in this test.
		const saving = new Promise( () => {} );

		interestSelector( wrapper ).vm.$emit( 'saved', saving );

		expect( mockLoad ).toHaveBeenCalledWith( 20, saving );
	} );

	// The dialog reports each exit ("Done", "Cancel", the close button, Esc) as
	// update:open. It only emits "saved" when the selection changed.
	it( 'dismisses the notice when the selector opened from it closes without interests', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );
		await wrapper.find( '.growthexperiments-interests-notice__button' ).trigger( 'click' );

		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		await wrapper.vm.$nextTick();

		expect( interestSelector( wrapper ).props( 'open' ) ).toBe( false );
		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'growthexperiments-home-interests-notice-interacted', 1,
		);
	} );

	it( 'does not save the dismissal when the selector closes with interests', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );
		await wrapper.find( '.growthexperiments-interests-notice__button' ).trigger( 'click' );
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		interestSelector( wrapper ).vm.$emit( 'saved' );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockSaveOption ).not.toHaveBeenCalled();
	} );

	it( 'dismisses the notice when the selector opened from the menu closes without interests', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );
		wrapper.findComponent( { name: 'ModuleHeaderMenu' } ).vm.$emit( 'select', 'interests' );
		await wrapper.vm.$nextTick();
		expect( interestSelector( wrapper ).props( 'open' ) ).toBe( true );

		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'growthexperiments-home-interests-notice-interacted', 1,
		);
	} );

	it( 'keeps the notice when another panel closes', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );
		wrapper.findComponent( { name: 'ModuleHeaderMenu' } ).vm.$emit( 'select', 'about' );
		await wrapper.vm.$nextTick();

		wrapper.vm.closePanel();
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( true );
		expect( mockSaveOption ).not.toHaveBeenCalled();
	} );

	it( 'does not save the dismissal when the notice does not show', async () => {
		const wrapper = mountApp( null );
		wrapper.findComponent( { name: 'ModuleHeaderMenu' } ).vm.$emit( 'select', 'interests' );
		await wrapper.vm.$nextTick();

		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		await wrapper.vm.$nextTick();

		expect( mockSaveOption ).not.toHaveBeenCalled();
	} );

	it( 'hides the notice and saves the dismissal', async () => {
		const wrapper = mountApp( 'show-no-interests-notice' );

		await wrapper.find( '.cdx-message__dismiss-button' ).trigger( 'click' );

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'growthexperiments-home-interests-notice-interacted', 1,
		);
	} );

	it( 'logs an error when the dismissal is not saved', async () => {
		mockSaveOption.mockRejectedValue( 'http' );
		const wrapper = mountApp( 'show-no-interests-notice' );

		await wrapper.find( '.cdx-message__dismiss-button' ).trigger( 'click' );
		await flushPromises();

		expect( mw.errorLogger.logError ).toHaveBeenCalled();
	} );

	describe( 'focus after the notice goes', () => {
		beforeEach( () => {
			mockMenuFocus.mockClear();
		} );

		it( 'moves the focus to the menu button when the user dismisses the notice', async () => {
			const wrapper = mountApp( 'show-no-interests-notice' );

			await wrapper.find( '.cdx-message__dismiss-button' ).trigger( 'click' );
			await wrapper.vm.$nextTick();

			expect( mockMenuFocus ).toHaveBeenCalled();
		} );

		it( 'moves the focus to the menu button when the selector closes without interests', async () => {
			const wrapper = mountApp( 'show-no-interests-notice' );
			await wrapper.find( '.growthexperiments-interests-notice__button' ).trigger( 'click' );

			interestSelector( wrapper ).vm.$emit( 'update:open', false );
			await wrapper.vm.$nextTick();

			expect( mockMenuFocus ).toHaveBeenCalled();
		} );

		it( 'moves the focus to the menu button when interests are saved', async () => {
			const wrapper = mountApp( 'show-no-interests-notice' );
			mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

			interestSelector( wrapper ).vm.$emit( 'saved' );
			await wrapper.vm.$nextTick();

			expect( mockMenuFocus ).toHaveBeenCalled();
		} );

		it( 'does not move the focus when no notice shows', async () => {
			const wrapper = mountApp( null );
			mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

			interestSelector( wrapper ).vm.$emit( 'saved' );
			await wrapper.vm.$nextTick();

			expect( mockMenuFocus ).not.toHaveBeenCalled();
		} );
	} );
} );

describe( 'App types of edits menu icon', () => {
	/**
	 * @param {Object} wrapper
	 * @return {Object} the Types of edits menu item
	 */
	function taskTypesMenuItem( wrapper ) {
		return wrapper.findComponent( { name: 'ModuleHeaderMenu' } )
			.props( 'menuItems' )
			.find( ( item ) => item.value === 'taskTypes' );
	}

	beforeEach( () => {
		mockLoad.mockClear();
		mockFilters.getSelectedInterests.mockReturnValue( [] );
		mockFilters.getSelectedTaskTypes.mockReturnValue( [] );
	} );

	it.each( [
		[ [], 'level-none' ],
		[ [ 'copyedit' ], 'level-one' ],
		[ [ 'copyedit', 'links' ], 'level-one' ],
		[ [ 'references' ], 'level-two' ],
		[ [ 'copyedit', 'references' ], 'level-two' ],
		[ [ 'expand' ], 'level-three' ],
		[ [ 'copyedit', 'expand' ], 'level-three' ],
		[ [ 'references', 'expand' ], 'level-three' ],
	] )( 'uses the hardest difficulty icon for %j', ( selected, expectedIcon ) => {
		mockFilters.getSelectedTaskTypes.mockReturnValue( selected );
		const wrapper = mountApp( null );

		expect( taskTypesMenuItem( wrapper ).icon ).toBe( expectedIcon );
	} );

	it( 'updates the icon after the types dialog is saved', async () => {
		mockFilters.getSelectedTaskTypes.mockReturnValue( [ 'copyedit' ] );
		const wrapper = mountApp( null );
		expect( taskTypesMenuItem( wrapper ).icon ).toBe( 'level-one' );

		mockFilters.getSelectedTaskTypes.mockReturnValue( [ 'copyedit', 'expand' ] );
		wrapper.findComponent( { name: 'TaskTypesDialog' } ).vm.$emit( 'saved' );
		await wrapper.vm.$nextTick();

		expect( taskTypesMenuItem( wrapper ).icon ).toBe( 'level-three' );
		expect( mockLoad ).toHaveBeenCalledWith( 20 );
	} );
} );

describe( 'App welcome back dialog', () => {
	/**
	 * @param {Object} wrapper
	 * @return {Object} the wrapper of the welcome back dialog
	 */
	function welcomeBack( wrapper ) {
		return wrapper.findComponent( { name: 'WelcomeBackDialog' } );
	}

	beforeEach( () => {
		mockSaveOption.mockReset().mockResolvedValue( {} );
		mockFilters.getSelectedInterests.mockReturnValue( [] );
		global.mw.Api = jest.fn( () => ( { saveOption: mockSaveOption } ) );
		global.mw.errorLogger = { logError: jest.fn() };
	} );

	it( 'opens the dialog', () => {
		const wrapper = mountApp( 'show-welcome-back' );

		expect( welcomeBack( wrapper ).props( 'open' ) ).toBe( true );
	} );

	it.each( [
		'show-no-interests-notice',
		null,
	] )( 'does not open the dialog for the %s action', ( action ) => {
		const wrapper = mountApp( action );

		expect( welcomeBack( wrapper ).props( 'open' ) ).toBe( false );
	} );

	it( 'opens the interest selector from the dialog', async () => {
		const wrapper = mountApp( 'show-welcome-back' );

		welcomeBack( wrapper ).vm.$emit( 'select-interests' );
		await wrapper.vm.$nextTick();

		expect( welcomeBack( wrapper ).props( 'open' ) ).toBe( false );
		expect( interestSelector( wrapper ).props( 'open' ) ).toBe( true );
	} );

	it( 'keeps the notice when the user closes the dialog', async () => {
		const wrapper = mountApp( 'show-welcome-back' );

		welcomeBack( wrapper ).vm.$emit( 'update:open', false );
		await wrapper.vm.$nextTick();

		expect( welcomeBack( wrapper ).props( 'open' ) ).toBe( false );
		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( true );
		expect( mockSaveOption ).not.toHaveBeenCalledWith(
			'growthexperiments-home-interests-notice-interacted', 1,
		);
	} );

	it( 'dismisses the notice when the selector opened from the dialog closes without interests', async () => {
		const wrapper = mountApp( 'show-welcome-back' );
		welcomeBack( wrapper ).vm.$emit( 'select-interests' );
		await wrapper.vm.$nextTick();

		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'growthexperiments-home-interests-notice-interacted', 1,
		);
	} );

	it( 'hides the notice when the user saves interests from the dialog', async () => {
		const wrapper = mountApp( 'show-welcome-back' );
		welcomeBack( wrapper ).vm.$emit( 'select-interests' );
		await wrapper.vm.$nextTick();
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		interestSelector( wrapper ).vm.$emit( 'saved' );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
	} );
} );

describe( 'App instrumentation', () => {
	/**
	 * @param {Object} wrapper
	 * @return {Object} the wrapper of the module header menu
	 */
	function headerMenu( wrapper ) {
		return wrapper.findComponent( { name: 'ModuleHeaderMenu' } );
	}

	beforeEach( () => {
		mockLoad.mockClear();
		mockInstrumentation.recordModuleShownAfterVisibleOnce.mockClear();
		mockInstrumentation.sendClick.mockClear();
		mockInstrumentation.sendModuleReady.mockClear();
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion', 'Tulip' ] );
		mockFilters.getSelectedTaskTypes.mockReturnValue( [ 'copyedit' ] );
	} );

	afterEach( () => {
		mockFeedState.items = [];
		mockFeedState.error = null;
	} );

	it( 'sends module_ready and module_shown after the feed loads', async () => {
		mountApp( null );
		expect( mockInstrumentation.sendModuleReady ).not.toHaveBeenCalled();
		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).not.toHaveBeenCalled();

		await flushPromises();

		expect( mockInstrumentation.sendModuleReady ).toHaveBeenCalledTimes( 1 );
		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledTimes( 1 );
		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledWith( {
			// eslint-disable-next-line camelcase
			has_suggestions: false,
			fallback: 'none_found',
			interests: 2,
			// eslint-disable-next-line camelcase
			task_types: 1,
			// eslint-disable-next-line camelcase
			has_error: false,
		} );
	} );

	it( 'sends has_suggestions when the feed has tasks', async () => {
		mockLoad.mockImplementationOnce( () => {
			mockFeedState.items = [ { title: 'Dandelion', tasktype: 'copyedit' } ];
			return Promise.resolve();
		} );
		mountApp( null );

		await flushPromises();

		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledWith(
			// eslint-disable-next-line camelcase
			expect.objectContaining( { has_suggestions: true, fallback: 'not_used' } ),
		);
	} );

	it( 'sends has_error when the feed fails to load', async () => {
		mockLoad.mockImplementationOnce( () => {
			mockFeedState.error = new Error( 'http' );
			return Promise.resolve();
		} );
		mountApp( null );

		await flushPromises();

		expect( mockInstrumentation.sendModuleReady ).toHaveBeenCalledTimes( 1 );
		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledWith(
			// eslint-disable-next-line camelcase
			expect.objectContaining( { has_suggestions: false, fallback: 'not_used', has_error: true } ),
		);
	} );

	it( 'sends a click and closes the popover when the menu opens', () => {
		const wrapper = mountApp( null );
		wrapper.vm.shouldPopoverBeOpen = true;

		headerMenu( wrapper ).vm.$emit( 'menu-open' );

		expect( mockInstrumentation.sendClick ).toHaveBeenCalledWith( 'module_suggested_edits_options' );
		expect( wrapper.vm.shouldPopoverBeOpen ).toBe( false );
	} );

	it.each( [
		[ 'interests', 'module_suggested_edits_option_interests' ],
		[ 'taskTypes', 'module_suggested_edits_option_types' ],
	] )( 'sends a click when the user selects the "%s" menu item', ( menuValue, friendlyName ) => {
		const wrapper = mountApp( null );

		headerMenu( wrapper ).vm.$emit( 'select', menuValue );

		expect( mockInstrumentation.sendClick ).toHaveBeenCalledTimes( 1 );
		expect( mockInstrumentation.sendClick ).toHaveBeenCalledWith( friendlyName );
	} );

	it( 'sends no click when the user selects the about menu item', () => {
		const wrapper = mountApp( null );

		headerMenu( wrapper ).vm.$emit( 'select', 'about' );

		expect( mockInstrumentation.sendClick ).not.toHaveBeenCalled();
	} );

	it( 'sends a click with the task type when the user clicks a task card', async () => {
		mockFeedState.items = [
			{ title: 'Dandelion', tasktype: 'copyedit' },
			{ title: 'Tulip', tasktype: 'references' },
		];
		const wrapper = mountApp( null );

		await wrapper.findAllComponents( { name: 'TaskCard' } )[ 1 ].trigger( 'click' );

		expect( mockInstrumentation.sendClick ).toHaveBeenCalledWith(
			'module_suggested_edits_suggestion',
			// eslint-disable-next-line camelcase
			{ suggestion_type: 'references' },
		);
	} );

	it( 'sends a click and module_shown again when the types dialog is saved', async () => {
		const wrapper = mountApp( null );
		await flushPromises();
		mockInstrumentation.recordModuleShownAfterVisibleOnce.mockClear();
		mockFilters.getSelectedTaskTypes.mockReturnValue( [ 'copyedit', 'links', 'expand' ] );

		wrapper.findComponent( { name: 'TaskTypesDialog' } ).vm.$emit( 'saved' );
		await flushPromises();

		expect( mockInstrumentation.sendClick ).toHaveBeenCalledWith(
			'module_suggested_edits_option_types_done',
		);
		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledWith( {
			// eslint-disable-next-line camelcase
			has_suggestions: false,
			fallback: 'none_found',
			interests: 2,
			// eslint-disable-next-line camelcase
			task_types: 3,
			// eslint-disable-next-line camelcase
			has_error: false,
		} );
		expect( mockInstrumentation.sendModuleReady ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'sends a click and module_shown again when interests are saved', async () => {
		const wrapper = mountApp( null );
		await flushPromises();
		mockInstrumentation.recordModuleShownAfterVisibleOnce.mockClear();
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

		interestSelector( wrapper ).vm.$emit( 'saved' );
		await flushPromises();

		expect( mockInstrumentation.sendClick ).toHaveBeenCalledWith(
			'module_suggested_edits_option_interests_done',
		);
		expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledWith( {
			// eslint-disable-next-line camelcase
			has_suggestions: false,
			fallback: 'none_found',
			interests: 1,
			// eslint-disable-next-line camelcase
			task_types: 1,
			// eslint-disable-next-line camelcase
			has_error: false,
		} );
		expect( mockInstrumentation.sendModuleReady ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'App filter dialogs', () => {
	it( 'gives both dialogs the count of the feed', () => {
		const wrapper = mountApp( null );

		expect( wrapper.findComponent( { name: 'TaskTypesDialog' } ).props( 'getSavedCount' ) )
			.toBe( mockGetSavedCount );
		expect( interestSelector( wrapper ).props( 'getSavedCount' ) ).toBe( mockGetSavedCount );
	} );
} );

describe( 'App no match notice', () => {
	/**
	 * @param {Object} wrapper
	 * @return {Object} the wrapper of the no match notice
	 */
	function noMatchNotice( wrapper ) {
		return wrapper.findComponent( { name: 'NoMatchNotice' } );
	}

	beforeEach( () => {
		mockLoad.mockClear();
		mockMenuFocus.mockClear();
		mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );
		mockFilters.getSelectedTaskTypes.mockReturnValue( [ 'copyedit' ] );
		mw.storage.session.get.mockReset().mockReturnValue( null );
		mw.storage.session.set.mockClear();
		mockFeedState.isLoading = false;
	} );

	afterEach( () => {
		mockFeedState.isLoading = true;
		mockFeedState.items = [];
		mockFeedState.error = null;
	} );

	it( 'shows the notice instead of the empty state when no tasks match', () => {
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( true );
		expect( wrapper.findComponent( { name: 'FeedPanel' } ).props( 'emptyLabel' ) ).toBe( '' );
	} );

	it( 'does not show the notice while the first load continues', () => {
		mockFeedState.isLoading = true;
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'does not show the notice for the empty feed before the first load', () => {
		mockLoad.mockImplementationOnce( () => {
			mockFeedState.isLoading = true;
			return new Promise( () => {} );
		} );
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'does not save the interests notice dismissal when the no match notice replaces it', async () => {
		mockFilters.getSelectedInterests.mockReturnValue( [] );
		mockSaveOption.mockReset().mockResolvedValue( {} );
		global.mw.Api = jest.fn( () => ( { saveOption: mockSaveOption } ) );
		const wrapper = mountApp( 'show-no-interests-notice' );

		noMatchNotice( wrapper ).vm.$emit( 'select-interests' );
		await flushPromises();
		interestSelector( wrapper ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( mockSaveOption ).not.toHaveBeenCalled();
		expect( mockMenuFocus ).not.toHaveBeenCalled();
	} );

	it( 'does not show the notice when tasks match', () => {
		mockFeedState.items = [ { id: 'Dandelion', title: 'Dandelion', tasktype: 'copyedit' } ];
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows the notice with the fallback items', () => {
		mockFeedState.items = [ { id: 'Tulip', title: 'Tulip', tasktype: 'copyedit', isFallback: true } ];
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( true );
		expect( wrapper.findAllComponents( { name: 'TaskCard' } ) ).toHaveLength( 1 );
	} );

	it( 'hides the notice when the next load gives matching tasks instead of fallback items', async () => {
		mockFeedState.items = [ { id: 'Tulip', title: 'Tulip', tasktype: 'copyedit', isFallback: true } ];
		const wrapper = mountApp( null );

		mockFeedState.isLoading = true;
		await flushPromises();
		mockFeedState.items = [ { id: 'Dandelion', title: 'Dandelion', tasktype: 'copyedit', isFallback: false } ];
		mockFeedState.isLoading = false;
		await flushPromises();

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'keeps the fallback items when the user dismisses the notice', async () => {
		mockFeedState.items = [ { id: 'Tulip', title: 'Tulip', tasktype: 'copyedit', isFallback: true } ];
		const wrapper = mountApp( null );

		noMatchNotice( wrapper ).vm.$emit( 'dismiss' );
		await flushPromises();

		expect( wrapper.findAllComponents( { name: 'TaskCard' } ) ).toHaveLength( 1 );
	} );

	it.each( [ 'selected_task_types', 'all_task_types' ] )(
		'sends the %s fallback tier and no suggestions when the feed shows fallback items',
		async ( fallbackTier ) => {
			mockInstrumentation.recordModuleShownAfterVisibleOnce.mockClear();
			mockFeedState.items = [
				{ id: 'Tulip', title: 'Tulip', tasktype: 'copyedit', isFallback: true, fallbackTier },
			];
			mountApp( null );

			await flushPromises();

			expect( mockInstrumentation.recordModuleShownAfterVisibleOnce ).toHaveBeenCalledWith(
				// eslint-disable-next-line camelcase
				expect.objectContaining( { has_suggestions: false, fallback: fallbackTier } ),
			);
		},
	);

	it( 'does not show the notice when the load fails', () => {
		mockFeedState.error = new Error( 'http' );
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'keeps the notice while the feed loads again', async () => {
		const wrapper = mountApp( null );

		mockFeedState.isLoading = true;
		await flushPromises();

		expect( noMatchNotice( wrapper ).exists() ).toBe( true );
	} );

	it( 'hides the notice when the next load gives tasks', async () => {
		const wrapper = mountApp( null );

		mockFeedState.isLoading = true;
		await flushPromises();
		mockFeedState.items = [ { id: 'Dandelion', title: 'Dandelion', tasktype: 'copyedit' } ];
		mockFeedState.isLoading = false;
		await flushPromises();

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'opens the interest selector from the notice', async () => {
		const wrapper = mountApp( null );

		noMatchNotice( wrapper ).vm.$emit( 'select-interests' );
		await flushPromises();

		expect( interestSelector( wrapper ).props( 'open' ) ).toBe( true );
	} );

	it( 'opens the types of edits dialog from the notice', async () => {
		const wrapper = mountApp( null );

		noMatchNotice( wrapper ).vm.$emit( 'select-task-types' );
		await flushPromises();

		expect( wrapper.findComponent( { name: 'TaskTypesDialog' } ).props( 'open' ) ).toBe( true );
	} );

	it( 'hides the notice for the session and shows the empty state when the user dismisses it', async () => {
		const wrapper = mountApp( null );

		noMatchNotice( wrapper ).vm.$emit( 'dismiss' );
		await flushPromises();

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
		expect( mw.storage.session.set ).toHaveBeenCalledWith(
			'ge-home-suggestededits-nomatch-notice-dismissed', '1',
		);
		expect( wrapper.findComponent( { name: 'FeedPanel' } ).props( 'emptyLabel' ) )
			.toBe( 'growthexperiments-home-suggestededits-empty' );
	} );

	it( 'does not show the notice when the user dismissed it in this session', () => {
		mw.storage.session.get.mockReturnValue( '1' );
		const wrapper = mountApp( null );

		expect( noMatchNotice( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows the no match notice instead of the interests notice', () => {
		mockFilters.getSelectedInterests.mockReturnValue( [] );
		const wrapper = mountApp( 'show-no-interests-notice' );

		expect( noMatchNotice( wrapper ).exists() ).toBe( true );
		expect( wrapper.findComponent( { name: 'InterestsNotice' } ).exists() ).toBe( false );
	} );

	it( 'moves the focus to the menu button when the notice goes with the focus', async () => {
		const wrapper = mountApp( null, { attachTo: document.body } );
		wrapper.find( '.growthexperiments-nomatch-notice button' ).element.focus();

		noMatchNotice( wrapper ).vm.$emit( 'dismiss' );
		await flushPromises();

		expect( mockMenuFocus ).toHaveBeenCalledTimes( 1 );
		wrapper.unmount();
	} );

	it( 'does not move the focus when the notice goes without the focus', async () => {
		const wrapper = mountApp( null );

		noMatchNotice( wrapper ).vm.$emit( 'dismiss' );
		await flushPromises();

		expect( mockMenuFocus ).not.toHaveBeenCalled();
	} );
} );
