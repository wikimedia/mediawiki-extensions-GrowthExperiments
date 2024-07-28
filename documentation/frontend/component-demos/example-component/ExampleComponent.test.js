import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import { CdxButton } from '@wikimedia/codex';
import ExampleComponent from './ExampleComponent.vue';

const renderComponent = () => mount( ExampleComponent, {
	global: {
		plugins: [ createTestingPinia( { stubActions: false } ) ]
	}
} );

describe( 'ExampleComponent', () => {
	it( 'renders correctly', () => {
		const wrapper = renderComponent();
		expect( wrapper.text() ).toContain( 'Count is 0' );
		const button = wrapper.findComponent( CdxButton );
		expect( button ).toBeDefined();
		expect( button.text() ).toContain( 'Increment' );
	} );
	it( 'increments correctly', () => {
		const wrapper = renderComponent();
		expect( wrapper.text() ).toContain( 'Count is 0' );
		const button = wrapper.findComponent( CdxButton );
		Promise.all( [
			button.trigger( 'click' ),
			button.trigger( 'click' )
		] ).then( () => {
			expect( wrapper.text() ).toContain( 'Count is 2' );
		} );
	} );
} );
