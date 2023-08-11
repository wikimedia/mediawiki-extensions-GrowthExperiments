const { mount } = require( '@vue/test-utils' );
const PersonalizedPraiseSettingsForm = require( './PersonalizedPraiseSettingsForm.vue' );

describe( 'PersonalizedPraiseSettingsForm', () => {

	it( 'it renders with correct defaults', () => {
		const wrapper = mount( PersonalizedPraiseSettingsForm, {
			props: {
				minEdits: 1,
				days: 7,
				messageSubject: '',
				messageText: '',
				notificationFrequency: 3
			},
			global: {
				provide: {
					RENDER_MODE: 'desktop'
				}
			}
		} );
		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
