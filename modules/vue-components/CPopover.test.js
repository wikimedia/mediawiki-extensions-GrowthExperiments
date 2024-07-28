const { shallowMount } = require( '@vue/test-utils' );
const CPopover = require( './CPopover.vue' );

const renderComponent = ( props = {
	title: 'Some title',
	closeIcon: 'Close icon',
	closeIconLabel: 'Close label',
	headerIcon: 'Header icon',
	headerIconLabel: 'Header icon label'
}, slots = {
	trigger: '<button>Click me</button>',
	content: '<p>Some fancy content</p>'
} ) => shallowMount( CPopover, {
	props,
	slots
} );

describe( 'CPopover', () => {
	it( 'should be closed by default', () => {
		const wrapper = renderComponent();
		expect( wrapper.element ).toMatchSnapshot();
	} );

	it( 'should display slot content when opened', () => {
		const wrapper = renderComponent();

		wrapper.vm.togglePopover();
		wrapper.vm.$nextTick().then( () => {
			expect( wrapper.element ).toMatchSnapshot();
			expect( wrapper.emitted() ).toHaveProperty( 'open' );
		} );
	} );

	it( 'should hide slot content when closed', () => {
		const wrapper = renderComponent();

		wrapper.vm.close();
		wrapper.vm.$nextTick().then( () => {
			expect( wrapper.element ).toMatchSnapshot();
			expect( wrapper.emitted() ).toHaveProperty( 'close' );
		} );
	} );

	it( 'should hide content when pressing on esc', () => {
		const wrapper = renderComponent();

		wrapper.vm.togglePopover();
		wrapper.vm.$nextTick()
			.then( () => wrapper.find( '.ext-growthExperiments-Popover__surface-container' ).trigger( 'keyup.esc' ) )
			.then( () => {
				expect( wrapper.element ).toMatchSnapshot();
				expect( wrapper.emitted() ).toHaveProperty( 'close' );
			} );
	} );
} );
