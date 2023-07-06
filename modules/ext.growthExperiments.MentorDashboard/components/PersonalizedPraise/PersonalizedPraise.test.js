jest.mock( '../../../vue-components/icons.json', () => ( {
	cdxIconNext: '',
	cdxIconPrevious: '',
	cdxIconSettings: '',
	cdxIconUserAvatar: ''
} ), { virtual: true } );
const { mount, shallowMount } = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const PersonalizedPraise = require( './PersonalizedPraise.vue' );
const praiseworthyMenteeJSON = require( '../../../../tests/qunit/__mocks__/praiseworthyMentee.json' );

describe( 'PersonalizedPraise', () => {
	let praiseworthyMenteesActions, praiseworthyMenteesGetters;
	let hasData = false;
	let store;

	beforeEach( () => {
		praiseworthyMenteesActions = {
			fetchMentees: jest.fn( () => $.Deferred().resolve().promise() ),
			previousPage: jest.fn( () => $.Deferred().resolve().promise() ),
			nextPage: jest.fn( () => $.Deferred().resolve().promise() ),
			saveSettings: jest.fn( () => $.Deferred().resolve().promise() )
		};
		praiseworthyMenteesGetters = {
			totalPages: jest.fn( () => {
				if ( hasData ) {
					return 1;
				} else {
					return 0;
				}
			} ),
			currentPage: jest.fn( () => {
				if ( hasData ) {
					return 1;
				} else {
					return 0;
				}
			} ),
			mentee: jest.fn( () => {
				if ( hasData ) {
					return praiseworthyMenteeJSON;
				} else {
					return undefined;
				}
			} ),
			settings: jest.fn( () => ( {
				minEdits: 1,
				days: 14,
				messageSubject: 'Message subject',
				messageText: 'Message text',
				notificationFrequency: 0
			} ) )
		};
		store = new Vuex.Store( {
			modules: {
				praiseworthyMentees: {
					actions: praiseworthyMenteesActions,
					getters: praiseworthyMenteesGetters,
					namespaced: true
				}
			}
		} );
	} );

	it( 'NoResult when no mentees', () => {
		hasData = false;
		const wrapper = mount( PersonalizedPraise, {
			global: {
				provide: {
					$log: jest.fn()
				},
				mocks: {
					log: jest.fn(),
					$store: store,
					$filters: {
						convertNumber: jest.fn( ( x ) => `${x}` )
					}
				}
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );

	it( 'full data when has mentees', () => {
		hasData = true;
		const wrapper = shallowMount( PersonalizedPraise, {
			global: {
				provide: {
					$log: jest.fn()
				},
				mocks: {
					log: jest.fn(),
					$store: store,
					$filters: {
						convertNumber: jest.fn( ( x ) => `${x}` )
					}
				}
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
