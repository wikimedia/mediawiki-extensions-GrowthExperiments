const { mount } = require( '@vue/test-utils' );
const MenteeFiltersForm = require( './MenteeFiltersForm.vue' );

describe( 'MenteeFiltersForm', () => {
	it( 'it should localise numbers in the recent edits', () => {
		const wrapper = mount( MenteeFiltersForm, {
			global: {
				mocks: {
					$filters: {
						convertNumber: jest.fn( ( x ) => `localised-${x}` )
					}
				}
			}
		} );

		expect( wrapper.element ).toMatchSnapshot();
	} );
} );
