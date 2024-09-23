jest.mock( './icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconUserTalk: '',
	cdxIconClock: '',
	cdxIconChart: '',
	cdxIconClose: '',
	cdxIconInfo: '',
	cdxIconInfoFilled: ''
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const ScoreCards = require( './CScoreCards.vue' );
const { useUserImpact } = require( '../ext.growthExperiments.Homepage.Impact/composables/useUserImpact.js' );
const jsonData = require( './__mocks__/serverExportedData.json' );

const renderComponent = ( { props = {}, provide = {} } = {} ) => mount( ScoreCards, {
	props: Object.assign( {
		userName: 'Alice',
		renderThirdPerson: false,
		hasIntl: true
	}, props ),
	provide: Object.assign( {
		RENDER_MODE: 'desktop'
	}, provide ),
	global: {
		provide: Object.assign( {
			$log: jest.fn()
		}, provide ),
		mocks: {
			$filters: {
				convertNumber: jest.fn( ( x ) => `${ x }` )
			}
		}
	}
} );

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
		mw.util.getUrl = jest.fn()
			.mockReturnValue( 'http://default.url' )
			.mockReturnValueOnce( 'http://contributions.url' )
			.mockReturnValueOnce( 'http://thanks.url' );
		const wrapper = renderComponent( {
			props: {
				data: null
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
	it( 'renders correctly with data', () => {
		mw.util.getUrl = jest.fn()
			.mockReturnValue( 'http://default.url' )
			.mockReturnValueOnce( 'http://contributions.url' )
			.mockReturnValueOnce( 'http://thanks.url' );
		const wrapper = renderComponent( {
			props: {
				data: useUserImpact( 60, jsonData ).value
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
