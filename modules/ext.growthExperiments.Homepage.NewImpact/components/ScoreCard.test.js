jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconClose: '',
	cdxIconInfo: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const ScoreCard = require( './ScoreCard.vue' );

describe( 'ScoreCard', () => {
	it( 'renders correctly', () => {
		const wrapper = mount( ScoreCard, {
			props: {
				icon: 'some icon',
				iconLabel: 'some icon label',
				label: 'some label',
				infoIconLabel: 'the label for the info icon'
			},
			global: {
				provide: {
					RENDER_MODE: 'desktop'
				}
			},
			slots: {
				default: '123'
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
	it( 'renders correctly info slot', () => {
		const wrapper = mount( ScoreCard, {
			props: {
				icon: 'some icon',
				iconLabel: 'some icon label',
				label: 'some label',
				infoIconLabel: 'the label for the info icon'
			},
			global: {
				provide: {
					RENDER_MODE: 'desktop'
				}
			},
			slots: {
				default: '123',
				'info-content': '<p>Some info text</p>'
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
