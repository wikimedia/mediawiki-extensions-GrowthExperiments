import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AddLinkDialog from './AddLinkDialog.vue';

describe( 'AddLinkDialog', () => {
	it( 'should render 3 step with Add a link on-boarding content', () => {
		const wrapper = mount( AddLinkDialog, {
			props: { open: true }
		} );
		expect( wrapper.html() ).toMatchSnapshot();
	} );
	it( 'should render second step with Add a link on-boarding content', () => {
		const wrapper = mount( AddLinkDialog, {
			props: {
				open: true,
				initialStep: 2
			}
		} );
		expect( wrapper.html() ).toMatchSnapshot();
	} );
	it( 'should render third step with Add a link on-boarding content', () => {
		const wrapper = mount( AddLinkDialog, {
			props: {
				open: true,
				initialStep: 3
			}
		} );
		expect( wrapper.html() ).toMatchSnapshot();
	} );
} );
