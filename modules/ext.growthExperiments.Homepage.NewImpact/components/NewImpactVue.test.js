jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconHeart: '',
	cdxIconClock: '',
	cdxIconChart: '',
	cdxIconClose: '',
	cdxIconInfo: '',
	cdxIconInfoFilled: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const NewImpact = require( './NewImpact.vue' );
const ScoreCard = require( './ScoreCard.vue' );
const RecentActivity = require( './RecentActivity.vue' );
const TrendChart = require( './TrendChart.vue' );

describe( 'NewImpactVue', () => {
	it( 'displays two scorecards', ( done ) => {
		global.mw.Rest.prototype.get = jest.fn( () => Promise.resolve( {
			editCountByNamespace: {
				3: 12,
				4: 9
			},
			editCountByDay: {},
			dailyTotalViews: {}
		} ) );
		const wrapper = mount( NewImpact, {
			global: {
				mocks: {
					$i18n: jest.fn( ( x ) => x ),
					$filters: {
						convertNumber: jest.fn( ( x ) => `${x}` )
					}
				}
			}
		} );
		wrapper.vm.$nextTick( () => {
			expect( wrapper.findAllComponents( ScoreCard ) ).toHaveLength( 4 );
			expect( wrapper.findAllComponents( RecentActivity ) ).toHaveLength( 1 );
			expect( wrapper.findAllComponents( TrendChart ) ).toHaveLength( 1 );
			done();
		} );
	} );
} );
