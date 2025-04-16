jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconUserTalk: '',
	cdxIconClock: '',
	cdxIconChart: '',
	cdxIconClose: 'Some truthy icon',
	cdxIconInfo: '',
	cdxIconInfoFilled: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const Impact = require( './Impact.vue' );
const CScoreCard = require( '../../vue-components/CScoreCard.vue' );
const RecentActivity = require( './RecentActivity.vue' );
const TrendChart = require( './TrendChart.vue' );
const ArticlesList = require( './ArticlesList.vue' );
const { useUserImpact } = require( '../composables/useUserImpact.js' );
const { DEFAULT_STREAK_TIME_FRAME } = require( '../constants.js' );

const impactServerData = () => ( {
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
		'2022-12-14': 100
	},
	totalPageviewsCount: 100,
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
	topViewedArticlesCount: 12
} );

const NO_PERSON_KEYS = [
	'growthexperiments-homepage-impact-edited-articles-trend-chart-title',
	'growthexperiments-homepage-impact-contributions-link'
];
const THIRD_PERSON_KEYS = [
	'growthexperiments-homepage-impact-recent-activity-title',
	'growthexperiments-homepage-impact-edited-articles-trend-chart-count-label',
	'growthexperiments-homepage-impact-subheader-text'
];
const ALL_KEYS = [ ...NO_PERSON_KEYS, ...THIRD_PERSON_KEYS ];

const renderComponent = ( { props = {}, provide = {} } = {} ) => {
	const mockData = useUserImpact( DEFAULT_STREAK_TIME_FRAME, impactServerData() );
	return mount( Impact, {
		props: Object.assign( { userName: 'Alice', data: mockData.value }, props ),
		global: {
			provide: Object.assign( {
				RENDER_MODE: 'desktop',
				RELEVANT_USER_USERNAME: 'Alice',
				RENDER_IN_THIRD_PERSON: false,
				BROWSER_HAS_INTL: true,
				IMPACT_MAX_EDITS: 1000,
				IMPACT_MAX_THANKS: 1000
			}, provide ),
			mocks: {
				$log: jest.fn(),
				$filters: {
					convertNumber: jest.fn( ( x ) => `${ x }` )
				}
			}
		}
	} );
};

describe( 'ImpactVue', () => {
	beforeEach( () => {
		global.mw.config.get = jest.fn();
		global.mw.config.get.mockImplementation( ( key ) => {
			switch ( key ) {
				case 'wgUserLanguage':
					return 'en';
				case 'wgTranslateNumerals':
					return false;
				case 'homepagemobile':
					return false;
				default:
					throw new Error( 'Unkown key: ' + key );
			}
		} );
	} );
	it( 'displays activated state layout', () => {
		const wrapper = renderComponent();
		expect( wrapper.findAllComponents( CScoreCard ) ).toHaveLength( 4 );
		expect( wrapper.findAllComponents( RecentActivity ) ).toHaveLength( 1 );
		expect( wrapper.findAllComponents( TrendChart ) ).toHaveLength( 1 );
		expect( wrapper.findAllComponents( ArticlesList ) ).toHaveLength( 1 );
		for ( const key of ALL_KEYS ) {
			expect( wrapper.text() ).toContain( key );
		}
	} );
	it( 'displays other person texts', () => {
		const wrapper = renderComponent( {
			provide: { RENDER_IN_THIRD_PERSON: true }
		} );
		for ( const key of NO_PERSON_KEYS ) {
			expect( wrapper.text() ).toContain( key );
		}
		for ( const key of THIRD_PERSON_KEYS ) {
			expect( wrapper.text() ).toContain( `${ key }-third-person` );
		}
	} );
	it( 'hides the recent activity section if Intl is not present', () => {
		const wrapper = renderComponent( {
			provide: {
				BROWSER_HAS_INTL: false
			}
		} );
		expect( wrapper.findAllComponents( RecentActivity ) ).toHaveLength( 0 );
		expect( wrapper.text() ).not.toContain( 'growthexperiments-homepage-impact-recent-activity-title' );
	} );
} );
