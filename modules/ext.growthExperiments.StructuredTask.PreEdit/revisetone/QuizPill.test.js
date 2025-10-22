'use strict';
jest.mock( '../common/codex-icons.json', () => ( {
	cdxIconSuccess: 'Success icon',
	cdxIconClear: 'Clear icon',
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const QuizPill = require( './QuizPill.vue' );
const mwLanguageMock = {
	convertNumber: ( x ) => String( x ),
	getFallbackLanguageChain: () => ( [ 'en' ] ),
};
describe( 'QuizPill', () => {
	it( 'renders interactable by default', () => {
		const wrapper = mount( QuizPill, {
			props: {
				label: 'Some label',
				iconNumber: 1,
			},
			global: {
				provide: {
					'mw.language': mwLanguageMock,
				},
			},
		} );
		expect( wrapper.text() ).toContain( 'Some label' );
		// TODO lookup the number in the right markup not just by text
		expect( wrapper.text() ).toContain( '1' );
		expect(
			wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill-Pill' ).classes(),
		).toContain( 'ext-growthExperiments-ReviseTone-QuizPill-Pill--notice' );
		wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill' ).trigger( 'click' );
		expect( wrapper.emitted() ).toHaveProperty( 'click' );
	} );
	it( 'renders non-interactable when reveal is provided', () => {
		const wrapper = mount( QuizPill, {
			props: {
				label: 'Some label',
				iconNumber: 1,
				reveal: 'An answer', // The user selected a different pill, and this is not the correct answer
			},
			global: {
				provide: {
					'mw.language': mwLanguageMock,
				},
			},
		} );
		expect( wrapper.text() ).toContain( 'Some label' );
		expect( wrapper.text() ).toContain( '1' );
		expect(
			wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill-Pill' ).classes(),
		).toContain( 'ext-growthExperiments-ReviseTone-QuizPill-Pill--notice' );
		wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill' ).trigger( 'click' );
		expect( wrapper.emitted() ).toMatchObject( {} );
	} );
	it( 'renders non-interactable/correct when reveal is provided', () => {
		const wrapper = mount( QuizPill, {
			props: {
				label: 'Some label',
				correct: true,
				iconNumber: 1,
				reveal: 'Some label', // The user selected this pill, and this is the correct answer
			},
			global: {
				provide: {
					'mw.language': mwLanguageMock,
				},
			},
		} );
		expect( wrapper.text() ).toContain( 'Some label' );
		expect(
			wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill-Pill' ).classes(),
		).toContain( 'ext-growthExperiments-ReviseTone-QuizPill-Pill--success' );
		wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill' ).trigger( 'click' );
		expect( wrapper.emitted() ).toMatchObject( {} );
	} );
	it( 'renders non-interactable/incorrect when reveal is provided', () => {
		const wrapper = mount( QuizPill, {
			props: {
				label: 'Some label',
				iconNumber: 1,
				reveal: 'Some label', // The user selected this pill, and this is NOT the correct answer
			},
			global: {
				provide: {
					'mw.language': mwLanguageMock,
				},
			},
		} );
		expect( wrapper.text() ).toContain( 'Some label' );
		expect(
			wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill-Pill' ).classes(),
		).toContain( 'ext-growthExperiments-ReviseTone-QuizPill-Pill--error' );
		wrapper.find( '.ext-growthExperiments-ReviseTone-QuizPill' ).trigger( 'click' );
		expect( wrapper.emitted() ).toMatchObject( {} );
	} );
} );
