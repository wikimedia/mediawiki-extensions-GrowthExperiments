'use strict';
const { mount } = require( '@vue/test-utils' );
const OnboardingStepper = require( './OnboardingStepper.vue' );

const renderComponent = ( props ) => {
	const defaultProps = { totalSteps: 0, modelValue: 0, label: '' };
	const wrapper = mount( OnboardingStepper, {
		props: Object.assign( {}, defaultProps, props ),
	} );
	return wrapper;
};

describe( 'OnboardingStepper', () => {
	it( 'should render one dot for each step', () => {
		const wrapper = renderComponent( { modelValue: 1, totalSteps: 3 } );
		expect( wrapper.findAll( '.ext-growthExperiments-OnboardingStepper__dots__dot' ) )
			.toHaveLength( 3 );
		expect( wrapper.findAll( '.ext-growthExperiments-OnboardingStepper__dots__dot--active' ) )
			.toHaveLength( 1 );
	} );

	it( 'should not display text indicator if no text label is provided', () => {
		const wrapper = renderComponent( { modelValue: 1, totalSteps: 4 } );
		expect( wrapper.text() ).not.toContain( '2 of 4' );
	} );

	it( 'should include text indicator if label is provided', () => {
		const wrapper = renderComponent( { modelValue: 2, totalSteps: 4, label: '2 of 4' } );
		expect( wrapper.text() ).toContain( '2 of 4' );
	} );

	it( 'should react to "currentStep" prop changes', () => {
		const wrapper = renderComponent( { modelValue: 1, totalSteps: 3 } );
		wrapper.setProps( { modelValue: 2 } ).then( () => {
			expect( wrapper.findAll( '.ext-growthExperiments-OnboardingStepper__dots__dot--active' ) )
				.toHaveLength( 2 );
		} );
	} );
	it( 'should react to "label" prop changes', () => {
		const wrapper = renderComponent( { modelValue: 2, totalSteps: 4 } );
		wrapper.setProps( { label: '2 of 4' } ).then( () => {
			expect( wrapper.text() ).toContain( '2 of 4' );
		} );
	} );
} );
