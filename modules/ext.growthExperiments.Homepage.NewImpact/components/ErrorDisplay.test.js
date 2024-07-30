jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconUserTalk: '',
	cdxIconClock: '',
	cdxIconChart: '',
	cdxIconInfo: '',
	cdxIconInfoFilled: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const ErrorDisplay = require( './ErrorDisplay.vue' );

const renderComponent = ( props, provide ) => mount( ErrorDisplay, {
	props,
	components: {
		CText: require( '../../vue-components/CText.vue' ),
		CScoreCards: jest.fn( () => 'ScoreCardsMock' )
	},
	global: {
		provide: Object.assign( {
			RENDER_MODE: 'desktop'
		}, provide )
	}
} );

describe( 'ErrorDisplay', () => {
	const INPUT_DATA = [
		{
			provide: {
				RELEVANT_USER_USERNAME: 'Alice',
				RENDER_IN_THIRD_PERSON: false
			}
		},
		{
			provide: {
				RELEVANT_USER_USERNAME: 'Alice',
				RENDER_IN_THIRD_PERSON: true
			}
		}
	];
	for ( const input of INPUT_DATA ) {
		it( `displays appropriate text when disabled ( third person: ${ input.provide.RENDER_IN_THIRD_PERSON })`, () => {
			const wrapper = renderComponent( {}, input.provide );
			expect( wrapper.element ).toMatchSnapshot();
		} );
	}
} );
