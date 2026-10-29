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
	props: [ 'open' ],
	emits: [ 'update:open', 'saved' ],
	render: () => null,
} ) );
jest.mock( './InterestSelectorDialog.vue', () => ( {
	name: 'InterestSelectorDialog',
	props: [ 'open', 'stackedActions' ],
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
const mockFeedState = { items: [], isLoading: true, error: null };
jest.mock( './useSuggestedEditsFeed.js', () => ( {
	useSuggestedEditsFeed: () => ( {
		feedState: mockFeedState,
		load: mockLoad,
	} ),
} ) );
jest.mock( 'ext.personalDashboard.common', () => ( {
	FULL_LIMIT: 20,
	FeedCard: { render: () => null },
	FeedPanel: {
		props: [ 'items' ],
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
const mockInstrumentation = {
	recordModuleShownAfterVisibleOnce: jest.fn(),
	sendClick: jest.fn(),
	sendModuleReady: jest.fn(),
};

/**
 * @param {string|null} action Value of wgGEHomeOnboardingAction
 * @return {Object} the mounted wrapper
 */
function mountApp( action ) {
	mw.config.get.mockImplementation(
		( key ) => ( key === 'wgGEHomeOnboardingAction' ? action : undefined ),
	);
	return mount( App, {
		props: { headerTarget: '#header' },
		global: {
			provide: { HOME_MODULE_INSTRUMENTATION: mockInstrumentation },
			stubs: { teleport: true, CdxPopover: true },
		},
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

		interestSelector( wrapper ).vm.$emit( 'saved' );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( '.growthexperiments-interests-notice' ).exists() ).toBe( false );
		expect( mockLoad ).toHaveBeenCalledWith( 20 );
		expect( mockSaveOption ).not.toHaveBeenCalled();
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
		let menuButton;

		beforeEach( () => {
			// The module header that the server renders, with the menu button
			// that the real ModuleHeaderMenu puts in it.
			document.body.innerHTML =
				'<div id="header"><div class="personal-dashboard-module-menu__button">' +
				'<button class="cdx-button"></button></div></div>';
			menuButton = document.querySelector( '#header .cdx-button' );
		} );

		afterEach( () => {
			document.body.innerHTML = '';
		} );

		it( 'moves the focus to the menu button when the user dismisses the notice', async () => {
			const wrapper = mountApp( 'show-no-interests-notice' );

			await wrapper.find( '.cdx-message__dismiss-button' ).trigger( 'click' );
			await wrapper.vm.$nextTick();

			expect( document.activeElement ).toBe( menuButton );
		} );

		it( 'moves the focus to the menu button when the selector closes without interests', async () => {
			const wrapper = mountApp( 'show-no-interests-notice' );
			await wrapper.find( '.growthexperiments-interests-notice__button' ).trigger( 'click' );

			interestSelector( wrapper ).vm.$emit( 'update:open', false );
			await wrapper.vm.$nextTick();

			expect( document.activeElement ).toBe( menuButton );
		} );

		it( 'moves the focus to the menu button when interests are saved', async () => {
			const wrapper = mountApp( 'show-no-interests-notice' );
			mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

			interestSelector( wrapper ).vm.$emit( 'saved' );
			await wrapper.vm.$nextTick();

			expect( document.activeElement ).toBe( menuButton );
		} );

		it( 'does not move the focus when no notice shows', async () => {
			const wrapper = mountApp( null );
			mockFilters.getSelectedInterests.mockReturnValue( [ 'Dandelion' ] );

			interestSelector( wrapper ).vm.$emit( 'saved' );
			await wrapper.vm.$nextTick();

			expect( document.activeElement ).toBe( document.body );
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
			expect.objectContaining( { has_suggestions: true } ),
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
			expect.objectContaining( { has_suggestions: false, has_error: true } ),
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
			interests: 1,
			// eslint-disable-next-line camelcase
			task_types: 1,
			// eslint-disable-next-line camelcase
			has_error: false,
		} );
		expect( mockInstrumentation.sendModuleReady ).toHaveBeenCalledTimes( 1 );
	} );
} );
