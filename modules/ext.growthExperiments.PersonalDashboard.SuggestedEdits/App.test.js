'use strict';

/*
 * CodexModule generates codex.js and icons.json at ResourceLoader time,
 * InterestSelectorDialog.vue is mapped in from another directory, and
 * ext.personalDashboard.common comes from a sibling extension, so none of them
 * exist here on disk for jest to resolve. The panels and the feed are stubbed:
 * these tests only cover the interests notice.
 */
jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );
jest.mock( './icons.json', () => ( {
	cdxIconChartBar: '<path d="M1"/>',
	cdxIconConfigure: '<path d="M2"/>',
} ), { virtual: true } );
jest.mock( './AboutPanel.vue', () => ( { render: () => null } ) );
jest.mock( './TaskCard.vue', () => ( { render: () => null } ) );
jest.mock( './TaskTypesDialog.vue', () => ( { render: () => null } ) );
jest.mock( './InterestSelectorDialog.vue', () => ( {
	name: 'InterestSelectorDialog',
	props: [ 'open', 'stackedActions' ],
	emits: [ 'update:open', 'saved' ],
	render: () => null,
} ), { virtual: true } );

const mockLoad = jest.fn();
jest.mock( './useSuggestedEditsFeed.js', () => ( {
	useSuggestedEditsFeed: () => ( {
		feedState: { items: [], isLoading: true, error: null },
		load: mockLoad,
	} ),
} ) );
jest.mock( 'ext.personalDashboard.common', () => ( {
	FULL_LIMIT: 20,
	FeedPanel: { render: () => null },
	ModuleHeaderMenu: {
		name: 'ModuleHeaderMenu',
		props: [ 'menuItems', 'footerItem', 'buttonLabel' ],
		emits: [ 'select' ],
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
	newcomerTasks: { filters: mockFilters },
} ), { virtual: true } );

const { mount, flushPromises } = require( '@vue/test-utils' );
const App = require( './App.vue' );

const mockSaveOption = jest.fn();

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
		global: { stubs: { teleport: true } },
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
