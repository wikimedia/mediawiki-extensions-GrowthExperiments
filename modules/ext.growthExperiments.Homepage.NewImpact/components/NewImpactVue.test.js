const { shallowMount } = require( '@vue/test-utils' );
const NewImpact = require( './NewImpact.vue' );

describe( 'NewImpactVue', () => {
	it( 'displays', () => {
		shallowMount( NewImpact );
	} );
} );
