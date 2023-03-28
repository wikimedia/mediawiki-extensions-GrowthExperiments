import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import OnboardingDialog from './OnboardingDialog.vue';

// eslint-disable-next-line default-param-last
const renderComponent = ( open = false, slots ) => {
	const defaultSlot = { body: 'This is the default content' };
	const wrapper = mount( OnboardingDialog, {
		props: {
			open
		},
		slots: Object.assign( {}, defaultSlot, slots )
	} );
	return wrapper;
};

describe( 'OnboardingDialog', () => {
	it( 'should open the dialog based on "open" prop state', () => {
		const wrapper = renderComponent( false, {
			title: '<h3>Onboarding title</h3>'
		} );
		expect( wrapper.text() ).to.not.contain( 'Onboarding title' );
		expect( wrapper.text() ).to.not.contain( 'This is the default content' );
		wrapper.setProps( { open: true } ).then( () => {
			expect( wrapper.text() ).toContain( 'Onboarding title' );
			expect( wrapper.text() ).toContain( 'This is the default content' );
		} );
	} );
} );
