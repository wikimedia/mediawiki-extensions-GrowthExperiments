import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { CdxButton } from '@wikimedia/codex';
import ExampleComponent from './ExampleComponent.vue';

describe( 'ExampleComponent', () => {
	it( 'renders correctly', () => {
		const wrapper = mount( ExampleComponent );
		expect( wrapper.text() ).toContain( 'Hello world' );
		expect( wrapper.text() ).toContain( 'Count is 0' );
		const button = wrapper.findComponent( CdxButton );
		expect( button ).toBeDefined();
		expect( button.text() ).toContain( 'increment' );
	} );
	it( 'increments correctly', () => {
		const wrapper = mount( ExampleComponent );
		expect( wrapper.text() ).toContain( 'Count is 0' );
		const button = wrapper.findComponent( CdxButton );
		button.trigger( 'click' ).then( () =>
			expect( wrapper.text() ).toContain( 'Count is 1' )
		);
	} );
} );
