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
const NoEditsDisplay = require( './NoEditsDisplay.vue' );
const CScoreCard = require( '../../vue-components/CScoreCard.vue' );

const renderComponent = ( props, renderMode = 'desktop' ) => {
	return mount( NoEditsDisplay, {
		props,
		global: {
			provide: {
				$log: jest.fn(),
				RENDER_MODE: renderMode
			},
			mocks: {
				$filters: {
					convertNumber: jest.fn( ( x ) => `${x}` )
				}
			}
		}
	} );
};

describe( 'NoEditsDisplay', () => {
	it( 'displays scorecards with thanks count ( desktop & overlay )', () => {
		const props = {
			userName: 'Alice',
			isDisabled: true,
			data: {
				receivedThanksCount: 123
			}
		};
		const desktopWrapper = renderComponent( props, 'desktop' );
		expect( desktopWrapper.findAllComponents( CScoreCard ) ).toHaveLength( 2 );
		expect( desktopWrapper.text() ).toContain( '123' );
		expect( desktopWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-description' );
		expect( desktopWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-subheader-text' );

		const overlayWrapper = renderComponent( props, 'mobile-overlay' );
		expect( overlayWrapper.findAllComponents( CScoreCard ) ).toHaveLength( 2 );
		expect( overlayWrapper.text() ).toContain( '123' );
		expect( overlayWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-description' );
		expect( overlayWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-subheader-text' );

		const summaryWrapper = renderComponent( props, 'mobile-summary' );
		expect( summaryWrapper.findAllComponents( CScoreCard ) ).toHaveLength( 0 );
		expect( summaryWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-description' );
		expect( summaryWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-subheader-text' );
	} );
	it( 'displays button to suggested edits and different text ( overlay )', () => {
		const overlayWrapper = renderComponent( {
			userName: 'Alice',
			isDisabled: false,
			isActivated: false,
			data: null
		}, 'mobile-overlay' );
		const button = overlayWrapper.get( '[data-link-id="impact-see-suggested-edits"]' );
		expect( button.text() ).toBe( 'growthexperiments-homepage-impact-unactivated-suggested-edits-link' );
		expect( overlayWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-suggested-edits-footer' );

	} );
	it( 'button triggers the startediting flow if not activated', () => {
		const wrapper = renderComponent( {
			userName: 'Alice',
			isDisabled: false,
			isActivated: false,
			data: null
		}, 'mobile-overlay' );
		const button = wrapper.get( '[data-link-id="impact-see-suggested-edits"]' );
		global.mw.track = jest.fn();

		button.trigger( 'click' );

		expect( global.mw.track ).toHaveBeenNthCalledWith(
			1,
			'growthexperiments.startediting',
			{ moduleName: 'impact', trigger: 'impact' }
		);
	} );
	it( 'button navigates to suggested edits if activated', () => {
		const wrapper = renderComponent( {
			userName: 'Alice',
			isDisabled: false,
			isActivated: true,
			data: null
		}, 'mobile-overlay' );
		const button = wrapper.get( '[data-link-id="impact-see-suggested-edits"]' );
		global.window.history.replaceState = jest.fn();
		global.window.dispatchEvent = jest.fn();

		button.trigger( 'click' );

		expect( global.window.history.replaceState ).toHaveBeenNthCalledWith( 1, null, null, '#/homepage/suggested-edits' );
		expect( global.window.dispatchEvent ).toHaveBeenNthCalledWith( 1, new HashChangeEvent( 'hashchange' ) );
	} );
} );
