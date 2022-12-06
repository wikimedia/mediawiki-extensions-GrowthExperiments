jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconUserTalk: '',
	cdxIconClock: '',
	cdxIconChart: '',
	cdxIconClose: '',
	cdxIconInfo: '',
	cdxIconInfoFilled: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const NoEditsDisplay = require( './NoEditsDisplay.vue' );

const renderComponent = ( props, renderMode = 'desktop' ) => {
	return mount( NoEditsDisplay, {
		props,
		global: {
			provide: {
				RENDER_MODE: renderMode
			},
			mocks: {
				$filters: {
					convertNumber: jest.fn( ( x ) => `${x}` )
				}
			}
		}
	} );
};

describe( 'NoEditsDisplay', () => {
	const modes = [
		'desktop',
		'overlay',
		'overlay-summary'
	];
	for ( const mode of modes ) {
		it( `displays appropiate text when disabled (${mode})`, () => {
			const wrapper = renderComponent( {
				userName: 'Alice',
				isDisabled: true
			}, mode );

			expect( wrapper.element ).toMatchSnapshot();
		} );
		it( `displays appropiate text when activated (${mode})`, () => {
			const wrapper = renderComponent( {
				userName: 'Alice',
				isActivated: true
			}, mode );

			expect( wrapper.element ).toMatchSnapshot();
		} );
	}
} );
