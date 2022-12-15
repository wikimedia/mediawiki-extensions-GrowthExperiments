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
const NewImpact = require( './NewImpact.vue' );
const ScoreCard = require( './ScoreCard.vue' );
const RecentActivity = require( './RecentActivity.vue' );
const TrendChart = require( './TrendChart.vue' );
const useUserImpact = require( '../composables/useUserImpact.js' );
const { DEFAULT_STREAK_TIME_FRAME } = require( '../constants.js' );

const impactServerData = () => {
	return {
		'@version': 5,
		userId: 15,
		userName: 'Newcomer12',
		receivedThanksCount: 0,
		editCountByNamespace: [
			4
		],
		editCountByDay: {
			'2022-12-14': 4
		},
		timeZone: [
			'System|0',
			0
		],
		newcomerTaskEditCount: 1,
		lastEditTimestamp: 1671044060,
		generatedAt: 1671099466,
		longestEditingStreak: {
			datePeriod: {
				start: '2022-12-14',
				end: '2022-12-14',
				days: 1
			},
			totalEditCountForPeriod: 4
		},
		totalEditsCount: 4,
		dailyTotalViews: {
			'2022-12-14': 0
		},
		recentEditsWithoutPageviews: {
			article1: {
				firstEditDate: '2022-12-14',
				newestEdit: '20221214185420'
			},
			article2: {
				firstEditDate: '2022-12-14',
				newestEdit: '20221214171252'
			},
			article3: {
				firstEditDate: '2022-12-14',
				newestEdit: '20221214171038'
			},
			article4: {
				firstEditDate: '2022-12-14',
				newestEdit: '20221214121242'
			}
		},
		topViewedArticles: [],
		topViewedArticlesCount: 0
	};
};

const impactData = ( userId, timeFrame ) => {
	global.mw.config.get = jest.fn();
	global.mw.config.get.mockImplementation( ( key ) => {
		switch ( key ) {
			case 'wgUserLanguage':
				return 'en';
			case 'wgTranslateNumerals':
				return false;
			case 'wgCanonicalSpecialPageName':
				return 'Homepage';
			case 'homepagemobile':
				return false;
			case 'homepagemodules':
				return {
					impact: {
						impact: impactServerData()
					}
				};
			case 'GENewImpactD3Enabled':
				return false;
			default:
				throw new Error( 'Unkown key: ' + key );
		}
	} );

	const { data } = useUserImpact( userId, timeFrame );
	return data.value;
};

describe( 'NewImpactVue', () => {
	it( 'displays four scorecards', ( done ) => {
		const wrapper = mount( NewImpact, {
			props: {
				data: impactData( 1, DEFAULT_STREAK_TIME_FRAME ),
				userName: 'Alice'
			},
			global: {
				provide: {
					RENDER_MODE: 'desktop',
					RELEVANT_USER_USERNAME: 'Alice'
				},
				mocks: {
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
