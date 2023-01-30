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
const ScoreCards = require( './ScoreCards.vue' );
const useUserImpact = require( '../composables/useUserImpact.js' );
const jsonData = require( '../__mocks__/serverExportedData.json' );

const renderComponent = ( { props = {}, provide = {} } = {} ) => {
	return mount( ScoreCards, {
		props,
		global: {
			provide: Object.assign( {
				RENDER_MODE: 'desktop',
				RELEVANT_USER_USERNAME: 'Alice',
				RENDER_IN_THIRD_PERSON: false,
				BROWSER_HAS_INTL: true,
				$log: jest.fn()
			}, provide ),
			mocks: {
				$filters: {
					convertNumber: jest.fn( ( x ) => `${x}` )
				}
			}
		}
	} );
};

describe( 'ScoreCards', () => {
	beforeAll( () => {
		// Moment will use Date.now() when parsing "lastEditTimestamp" with moment().fromNow().
		// The lastEditTimestamp in /__mocks__/serverExportedData.json is for the Dec 14 2022.
		const JS_DATE_A_MONTH_AFTER_LAST_EDIT = new Date( '2023-01-14' );
		jest.useFakeTimers();
		jest.setSystemTime( JS_DATE_A_MONTH_AFTER_LAST_EDIT );
	} );

	afterAll( () => {
		// Undo the forced time we applied earlier, reset to system default.
		jest.setSystemTime( jest.getRealSystemTime() );
		jest.useRealTimers();
	} );
	it( 'renders correctly without data', () => {
		const wrapper = renderComponent( {
			props: {
				thanksUrl: 'http://thanks.url',
				contributionsUrl: 'http://contributions.url',
				data: null
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
	it( 'renders correctly with data', () => {
		const wrapper = renderComponent( {
			props: {
				thanksUrl: 'http://thanks.url',
				contributionsUrl: 'http://contributions.url',
				data: useUserImpact( 60, jsonData ).value
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
