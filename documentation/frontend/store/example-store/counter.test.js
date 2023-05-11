import { describe, expect, it, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useCounterStore } from './counter.js';

describe( 'Counter', () => {
	beforeEach( () => {
		// creates a fresh pinia and make it active so it's automatically picked
		// up by any useStore() call without having to pass it to it:
		// `useStore(pinia)`
		setActivePinia( createPinia() );
	} );
	it( 'increments', () => {
		const store = useCounterStore();
		expect( store.count ).toBe( 0 );
		store.increment();
		expect( store.count ).toBe( 1 );
	} );
} );
