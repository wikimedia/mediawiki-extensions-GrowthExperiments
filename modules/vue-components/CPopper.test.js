const { mount } = require( '@vue/test-utils' );
const CPopper = require( './CPopper.vue' );

const renderComponent = ( { props, slots = { default: 'Some text' } } ) => {
	return mount( CPopper, {
		props,
		slots
	} );
};

/**
 * Mock the getBoundingClientRect and left
 * properties from the triggerRef.
 *
 * @param {number} clientHeight The height of the slotted trigger element
 * @param {number} left The x coordinate from the left side of the viewport
 * @param {{ left: number, right: number }} boundingRect A DOMRect-like object,
 * with left and right properties.
 * @return {{ clientHeight: number, left: number, getBoundingClientRect: Function }}
 */
const triggerRefMock = ( clientHeight, left, boundingRect ) => {

	return {
		clientHeight,
		left,
		getBoundingClientRect: jest.fn( () => boundingRect )
	};
};

describe( 'CPopper', () => {
	it( 'should render without a close button', () => {
		const wrapper = renderComponent( {
			props: {
				triggerRef: triggerRefMock( 300, 100, { left: 110, right: 290 } )
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );

	it( 'should render above of the trigger element', () => {
		const wrapper = renderComponent( {
			props: {
				triggerRef: triggerRefMock( 300, 100, { left: 110, right: 290 } ),
				icon: 'Some icon',
				iconLabel: 'Some label',
				placement: 'above'
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
