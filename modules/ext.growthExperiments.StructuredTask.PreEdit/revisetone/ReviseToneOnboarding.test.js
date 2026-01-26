'use strict';
const { shallowMount } = require( '@vue/test-utils' );
jest.mock( '../common/codex-icons.json', () => ( {
	cdxIconClose: 'Some truthy icon',
	cdxIconNext: '',
	cdxIconPrevious: '',
} ), { virtual: true } );
const ReviseToneOnboardingModule = require( './ReviseToneOnboarding.vue' );
const ReviseToneOnboarding = ReviseToneOnboardingModule;
describe( 'ReviseToneOnboarding', () => {
	it( 'marks the onboarding preference as seen when dialog is closed', () => {
		const saveOptionMock = jest.fn().mockResolvedValue();
		const ApiMock = jest.fn( () => ( {
			saveOption: saveOptionMock,
		} ) );
		// Use a minimal wrapper so we can call setup() without rendering the full template.
		const TestComponent = {
			render() {
				return null;
			},
			setup() {
				return ReviseToneOnboarding.setup( {
					prefName: 'growthexperiments-revisetone-onboarding',
				} );
			},
		};

		const wrapper = shallowMount( TestComponent, {
			global: {
				provide: {
					i18n: jest.fn( () => ( {
						text: jest.fn( () => '' ),
					} ) ),
					'mw.Api': ApiMock,
					'mw.hook': jest.fn( () => ( { fire: jest.fn() } ) ),
					'mw.language': jest.fn( () => ( {
						getFallbackLanguageChain: jest.fn( () => [ 'en' ] ),
					} ) ),
					'mw.track': jest.fn(),
					experiment: null,
				},
				stubs: {
					teleport: true,
				},
			},
		} );
		// Directly trigger the close behavior, bypassing DOM structure details
		wrapper.vm.reset( { closeSource: 'quiet' } );
		expect( saveOptionMock ).toHaveBeenCalledWith(
			'growthexperiments-revisetone-onboarding',
			'1',
		);
	} );
} );
