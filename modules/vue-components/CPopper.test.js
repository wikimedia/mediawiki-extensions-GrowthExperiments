const { mount } = require( '@vue/test-utils' );
const { ref } = require( 'vue' );
const CPopper = require( './CPopper.vue' );

const renderComponent = ( { props, slots = { default: 'Some text' } } ) => {
	return mount( CPopper, {
		props,
		slots
	} );
};

describe( 'CPopper', () => {
	it( 'should render without a close button', () => {
		const wrapper = renderComponent( {
			props: {
				triggerRef: ref( null )
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );

	it( 'should render with a close button', () => {
		const wrapper = renderComponent( {
			props: {
				triggerRef: ref( null ),
				icon: 'Some icon',
				iconLabel: 'Some label'
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
