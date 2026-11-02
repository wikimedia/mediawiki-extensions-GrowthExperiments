'use strict';

jest.mock( './icons.json', () => ( {
	cdxIconEdit: '',
	cdxIconUserTalk: '',
	cdxIconClock: '',
	cdxIconChart: '',
	cdxIconClose: '',
	cdxIconInfo: '',
	cdxIconInfoFilled: '',
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const { formatRelativeTimeOrDate } = jest.requireMock( 'mediawiki.DateFormatter' );
const ScoreCards = require( './CScoreCards.vue' );
const { useUserImpact } = require( '../ext.growthExperiments.Homepage.Impact/composables/useUserImpact.js' );
const jsonData = require( './__mocks__/serverExportedData.json' );

const renderComponent = ( { props = {}, provide = {} } = {} ) => mount( ScoreCards, {
	props: Object.assign( {
		userName: 'Alice',
		renderThirdPerson: false,
		hasIntl: true,
	}, props ),
	global: {
		provide: Object.assign( {
			RENDER_MODE: 'desktop',
			IMPACT_MAX_EDITS: 1000,
			IMPACT_MAX_THANKS: 1000,
			$log: jest.fn(),
		}, provide ),
		mocks: {
			$filters: {
				convertNumber: jest.fn( ( x ) => `${ x }` ),
			},
		},
	},
} );

describe( 'ScoreCards', () => {
	beforeAll( () => {
		mw.config.get = jest.fn( ( key ) => {
			if ( key === 'wgUserLanguage' ) {
				return 'en';
			}
		} );
		// formatRelativeTimeOrDate reads the current time.
		// The lastEditTimestamp in /__mocks__/serverExportedData.json is for 14 Dec 2022.
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
				data: null,
			},
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
	it( 'renders correctly with data', () => {
		mw.util.getUrl = jest.fn()
			.mockReturnValue( 'http://default.url' )
			.mockReturnValueOnce( 'http://contributions.url' )
			.mockReturnValueOnce( 'http://thanks.url' );
		formatRelativeTimeOrDate.mockClear();
		const wrapper = renderComponent( {
			props: {
				data: useUserImpact( 60, jsonData ).value,
			},
		} );
		expect( formatRelativeTimeOrDate ).toHaveBeenCalledWith(
			new Date( jsonData.lastEditTimestamp * 1000 ),
		);
		expect( wrapper.text() ).toContain( 'Mocked time ago' );
		expect( wrapper.element ).toMatchSnapshot();
	} );
	it( 'displays the formatted limit in the over-limit message', () => {
		const props = {
			data: {
				receivedThanksCount: 5000,
			},
			hasIntl: true,
		};

		const wrapper = mount( ScoreCards, {
			props,
			global: {
				provide: {
					RENDER_MODE: 'desktop',
					IMPACT_MAX_EDITS: 1000,
					IMPACT_MAX_THANKS: 2000,
				},
				mocks: {
					$i18n: ( key, ...args ) => ( {
						text: () => {
							if ( key === 'growthexperiments-homepage-impact-scores-over-limit' ) {
								return `${ args[ 0 ] }+`;
							}
							return key;
						},
					} ),
					$filters: {
						convertNumber: jest.fn( ( x ) => `${ x }` ),
					},
				},
			},
		} );

		expect( wrapper.vm.receivedThanksCount ).toBe( '2000+' );
	} );
	it( 'sends a click event for the total edits link', () => {
		const instrumentation = { sendClick: jest.fn() };
		const wrapper = renderComponent( {
			props: { data: useUserImpact( 60, jsonData ).value },
			provide: { HOME_MODULE_INSTRUMENTATION: instrumentation },
		} );

		wrapper.find( '[data-link-id="impact-total-edits"]' ).trigger( 'click' );

		expect( instrumentation.sendClick ).toHaveBeenCalledWith( 'module_impact_total_edits' );
	} );
	it( 'works without instrumentation, as on the mentor dashboard', () => {
		const wrapper = renderComponent( {
			props: { data: useUserImpact( 60, jsonData ).value },
		} );

		expect( () => wrapper.find( '[data-link-id="impact-total-edits"]' ).trigger( 'click' ) )
			.not.toThrow();
	} );
} );
