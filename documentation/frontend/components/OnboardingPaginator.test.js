import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import OnboardingPaginator from './OnboardingPaginator.vue';

const renderComponent = ( { totalSteps = 0, currentStep = 0 } = {} ) => {
	const wrapper = mount( OnboardingPaginator, {
		props: {
			totalSteps,
			currentStep
		}
	} );
	return wrapper;
};

describe( 'OnboardingPaginator', () => {
	it( 'should render one dot for each step', () => {
		const wrapper = renderComponent( { currentStep: 1, totalSteps: 4 } );
		expect( wrapper.text() ).toContain( '1 of 4' );
		expect( wrapper.findAll( '.ext-growthExperiments-OnboardingPaginator__dots__dot' ) )
			.toHaveLength( 4 );
		expect( wrapper.findAll( '.ext-growthExperiments-OnboardingPaginator__dots__dot--active' ) )
			.toHaveLength( 1 );
	} );

	it( 'should react to "currentStep" prop changes', () => {
		const wrapper = renderComponent( { currentStep: 2, totalSteps: 4 } );
		wrapper.setProps( { currentStep: 2 } ).then( () => {
			expect( wrapper.text() ).toContain( '2 of 4' );
		} );
	} );
} );
