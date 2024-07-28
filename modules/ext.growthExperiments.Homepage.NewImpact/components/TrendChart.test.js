const { mount } = require( '@vue/test-utils' );
const TrendChart = require( './TrendChart.vue' );

const renderComponent = ( { props = {}, provide = {} } = {} ) => mount( TrendChart, {
	props,
	global: {
		stubs: {
			CSparkline: true
		},
		provide: Object.assign( {
			RENDER_MODE: 'desktop',
			RELEVANT_USER_USERNAME: 'Alice',
			RENDER_IN_THIRD_PERSON: false,
			BROWSER_HAS_INTL: true
		}, provide ),
		mocks: {
			$filters: {
				convertNumber: jest.fn( ( x ) => `${ x }` )
			}
		}
	}
} );

describe( 'TrendChart', () => {
	it( 'shows pageviews in short number format', () => {
		global.mw.config.get = jest.fn();
		global.mw.config.get
		// mock homepagemobile to be true so number short format is applied
			.mockReturnValueOnce( true )
		// mock wgUserLanguage
			.mockReturnValueOnce( 'en' );
		const wrapper = renderComponent( {
			props: {
				id: 'some-id',
				countLabel: 'some-count-label',
				chartTitle: 'some-title',
				pageviewTotal: 123012000
			}
		} );

		expect( wrapper.text() ).toContain( 'some-count-label' );
		expect( wrapper.text() ).toContain( '123M' );
	} );
	it( 'shows pageviews in standard format if whithout Intl', () => {
		global.mw.config.get = jest.fn();
		global.mw.config.get
		// mock homepagemobile to be true so number short format is applied
			.mockReturnValueOnce( true )
		// mock wgUserLanguage
			.mockReturnValueOnce( 'en' );
		const wrapper = renderComponent( {
			props: {
				id: 'some-id',
				countLabel: 'some-count-label',
				chartTitle: 'some-title',
				pageviewTotal: 123012000
			},
			provide: {
				BROWSER_HAS_INTL: false
			}
		} );

		expect( wrapper.text() ).toContain( 'some-count-label' );
		expect( wrapper.text() ).toContain( '123012000' );
	} );
} );
