import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import DummyComponent from './DummyComponent.vue';

describe( 'DummyComponent', () => {
	it( 'renders correctly', () => {
		const wrapper = mount( DummyComponent );
		expect( wrapper.text() ).toContain( 'Hello world' );
	} );
} );
