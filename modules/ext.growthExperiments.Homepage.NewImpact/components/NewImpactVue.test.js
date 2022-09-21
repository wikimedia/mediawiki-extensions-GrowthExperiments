jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconHeart: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const NewImpact = require( './NewImpact.vue' );
const ScoreCard = require( './ScoreCard.vue' );

describe( 'NewImpactVue', () => {
	it( 'displays two scorecards', ( done ) => {
		global.mw.Rest.prototype.get = jest.fn( () => Promise.resolve( {
			editCountByNamespace: {
				3: 12,
				4: 9
			}
		} ) );
		const wrapper = mount( NewImpact, {
			global: {
				mocks: {
					$i18n: jest.fn( ( x ) => x )
				}
			}
		} );
		wrapper.vm.$nextTick( () => {
			expect( wrapper.findAllComponents( ScoreCard ) ).toHaveLength( 2 );
			done();
		} );
	} );
} );
