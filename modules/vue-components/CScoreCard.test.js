jest.mock( './icons.json', () => ( {
	cdxIconClose: 'Some truthy icon',
	cdxIconInfo: '',
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const ScoreCard = require( './CScoreCard.vue' );

describe( 'ScoreCard', () => {
	it( 'renders correctly', () => {
		const wrapper = mount( ScoreCard, {
			props: {
				icon: 'some icon',
				iconLabel: 'some icon label',
				label: 'some label',
				infoIconLabel: 'the label for the info icon',
			},
			global: {
				provide: {
					RENDER_MODE: 'desktop',
				},
			},
			slots: {
				default: '123',
			},
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
	it( 'renders correctly info slot', ( done ) => {
		const wrapper = mount( ScoreCard, {
			props: {
				icon: 'some icon',
				iconLabel: 'some icon label',
				label: 'some label',
				infoIconLabel: 'the label for the info icon',
			},
			global: {
				provide: {
					RENDER_MODE: 'desktop',
				},
			},
			slots: {
				default: '123',
				'info-content': '<p>Some info text</p>',
			},
		} );
		expect( wrapper.element ).toMatchSnapshot();
		// Assert the popover passed content manually instead of in the snapshot
		const button = wrapper.get( 'button' );
		button.trigger( 'click' );
		wrapper.vm.$nextTick( () => {
			expect( wrapper.text() ).toContain( 'Some info text' );
			done();
		} );
	} );
} );
