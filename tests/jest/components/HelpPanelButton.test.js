const Vue = require( 'vue' );
const VueTestUtils = require( '@vue/test-utils' );
const HelpPanelButton = require( '../../../modules/ui-components/vue/HelpPanelButton.vue' );

// will be needed when adding i18n plugin
const localVue = VueTestUtils.createLocalVue();

describe( 'HelpPanelButton', () => {
	it( 'displays some text', () => {
		const wrapper = VueTestUtils.shallowMount( HelpPanelButton, {
			localVue,
			propsData: {
				text: 'Some text'
			}
		} );

		const header = wrapper.find( 'h2' );
		expect( header.exists() ).toBe( true );
		expect( header.text() ).toBe( 'Some text' );
	} );
} );
