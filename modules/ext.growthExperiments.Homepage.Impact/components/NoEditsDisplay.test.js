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

const renderComponent = ( props, mocks = {}, renderMode = 'desktop' ) => mount( NoEditsDisplay, {
	props,
	global: {
		provide: {
			RENDER_MODE: renderMode,
			RENDER_IN_THIRD_PERSON: false,
			IMPACT_MAX_EDITS: 1000,
			IMPACT_MAX_THANKS: 1000
		},
		mocks: Object.assign( {
			$filters: {
				convertNumber: jest.fn( ( x ) => `${ x }` )
			}
		}, mocks )
	}
} );

describe( 'NoEditsDisplay', () => {
	it( 'displays scorecards with thanks count ( desktop & overlay )', () => {
		const props = {
			userName: 'Alice',
			isDisabled: true,
			data: {
				receivedThanksCount: 123
			}
		};
		const desktopWrapper = renderComponent( props, {}, 'desktop' );
		expect( desktopWrapper.findAllComponents( CScoreCard ) ).toHaveLength( 2 );
		expect( desktopWrapper.text() ).toContain( '123' );
		expect( desktopWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-description' );
		expect( desktopWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-subheader-text' );

		const overlayWrapper = renderComponent( props, {}, 'mobile-overlay' );
		expect( overlayWrapper.findAllComponents( CScoreCard ) ).toHaveLength( 2 );
		expect( overlayWrapper.text() ).toContain( '123' );
		expect( overlayWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-description' );
		expect( overlayWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-subheader-text' );

		const summaryWrapper = renderComponent( props, {}, 'mobile-summary' );
		expect( summaryWrapper.findAllComponents( CScoreCard ) ).toHaveLength( 0 );
		expect( summaryWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-description' );
		expect( summaryWrapper.text() ).toContain( 'growthexperiments-homepage-impact-unactivated-subheader-text' );
	} );
	it( 'displays correct info in scorecard tooltips', () => {
		const props = {
			userName: 'Alice',
			isDisabled: true,
			data: {
				receivedThanksCount: 123
			}
		};
		const wrapper = renderComponent( props, {
			$log: jest.fn()
		} );
		const scorecards = wrapper.findAllComponents( CScoreCard );
		scorecards[ 0 ].find( '.ext-growthExperiments-ScoreCards__info-button' ).trigger( 'click' );
		scorecards[ 1 ].find( '.ext-growthExperiments-ScoreCards__info-button' ).trigger( 'click' );
		wrapper.vm.$nextTick().then( () => {
			expect( wrapper.text() ).toContain(
				'growthexperiments-homepage-impact-scores-thanks-info-text:[,1000]'
			);
			expect( wrapper.text() ).toContain(
				'growthexperiments-homepage-impact-scores-best-streak-info-text:[,1000]'
			);
		} );
	} );
	it( 'displays button to suggested edits and different text ( overlay )', () => {
		const overlayWrapper = renderComponent( {
			userName: 'Alice',
			isDisabled: false,
			isActivated: false,
			data: null
		}, {}, 'mobile-overlay' );
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
		}, {}, 'mobile-overlay' );
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
		}, {}, 'mobile-overlay' );
		const button = wrapper.get( '[data-link-id="impact-see-suggested-edits"]' );
		global.window.history.replaceState = jest.fn();
		global.window.dispatchEvent = jest.fn();

		button.trigger( 'click' );

		expect( global.window.history.replaceState ).toHaveBeenNthCalledWith( 1, null, null, '#/homepage/suggested-edits' );
		expect( global.window.dispatchEvent ).toHaveBeenNthCalledWith( 1, new HashChangeEvent( 'hashchange' ) );
	} );
	it( 'logs scorecard interactions', () => {
		const logSpy = jest.fn();
		const wrapper = renderComponent( {
			userName: 'Alice',
			isDisabled: false,
			isActivated: true,
			data: null
		}, {
			$log: logSpy
		} );
		const scorecards = wrapper.findAllComponents( CScoreCard );
		scorecards[ 0 ].vm.$emit( 'open' );
		expect( logSpy ).toHaveBeenNthCalledWith( 1, 'impact', 'open-thanks-info-tooltip' );
		scorecards[ 0 ].vm.$emit( 'close' );
		expect( logSpy ).toHaveBeenNthCalledWith( 2, 'impact', 'close-thanks-info-tooltip' );
		scorecards[ 1 ].vm.$emit( 'open' );
		expect( logSpy ).toHaveBeenNthCalledWith( 3, 'impact', 'open-streak-info-tooltip' );
		scorecards[ 1 ].vm.$emit( 'close' );
		expect( logSpy ).toHaveBeenNthCalledWith( 4, 'impact', 'close-streak-info-tooltip' );
	} );
	it( 'displays different limits for edits and thanks in tooltips', () => {
		const props = {
			userName: 'Alice',
			isDisabled: true,
			data: {
				receivedThanksCount: 123
			}
		};

		const wrapper = mount( NoEditsDisplay, {
			props,
			global: {
				provide: {
					RENDER_MODE: 'desktop',
					RENDER_IN_THIRD_PERSON: false,
					IMPACT_MAX_EDITS: 5000,
					IMPACT_MAX_THANKS: 2000
				},
				mocks: {
					$filters: {
						convertNumber: jest.fn( ( x ) => `${ x }` )
					},
					$log: jest.fn(),
					$i18n: jest.fn( ( key, ...args ) => ( {
						text: () => `${ key }:[${ args.join( ',' ) }]`
					} ) )
				}
			}
		} );

		const scorecards = wrapper.findAllComponents( CScoreCard );
		scorecards[ 0 ].find( '.ext-growthExperiments-ScoreCards__info-button' ).trigger( 'click' );
		scorecards[ 1 ].find( '.ext-growthExperiments-ScoreCards__info-button' ).trigger( 'click' );

		return wrapper.vm.$nextTick().then( () => {
			// Thanks tooltip should use IMPACT_MAX_THANKS value
			expect( wrapper.text() ).toContain(
				'growthexperiments-homepage-impact-scores-thanks-info-text:[,2000]'
			);
			// Streak tooltip should use IMPACT_MAX_EDITS value
			expect( wrapper.text() ).toContain(
				'growthexperiments-homepage-impact-scores-best-streak-info-text:[,5000]'
			);
		} );
	} );
} );
