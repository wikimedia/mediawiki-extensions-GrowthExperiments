const { mount } = require( '@vue/test-utils' );
const CText = require( './CText.vue' );

// TODO assert for all render modes
const renderComponent = ( { props, slots = { default: 'Some text' }, RENDER_MODE = 'desktop' } ) => mount( CText, {
	props,
	slots,
	global: {
		provide: {
			RENDER_MODE,
		},
		mocks: {
			$filters: {
				convertNumber: jest.fn( ( x ) => `localised-${ x }` ),
			},
		},
	},
} );

const TEST_INPUTS = [
	{
		props: {},
	},
	{
		props: {
			color: 'subtle',
			size: 'l',
			weight: 'bold',
		},
	},
	{
		props: {
			size: [ 'xl', null, null ],
		},
	},
	{
		props: {},
		slots: {
			default: '<a href="#">Some link</a>',
		},
	},
];

describe( 'CText', () => {
	it( 'should render correctly', () => {
		for ( const props of TEST_INPUTS ) {
			const wrapper = renderComponent( props );
			expect( wrapper.element ).toMatchSnapshot();
		}
	} );
} );
