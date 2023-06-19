import { vi, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AddLinkDialog from './AddLinkDialog.vue';

const renderComponent = ( props ) => {
	const wrapper = mount( AddLinkDialog, {
		props: Object.assign( {}, props ),
		global: {
			directives: {
				'i18n-html'( el, binding ) {
					el.innerHTML = binding.arg;
				}
			},
			provide: {
				USER_USERNAME: 'Alice'
			},
			mocks: {
				$i18n: vi.fn( ( x, ...params ) => ( {
					text: vi.fn( () => `${x}:[${params.join( ',' )}]` )
				} ) )
			}
		}
	} );
	return wrapper;
};

describe( 'AddLinkDialog', () => {
	it( 'should render 3 step with Add a link on-boarding content', () => {
		const wrapper = renderComponent( { open: true } );

		expect( wrapper.html() ).toMatchSnapshot();
	} );
	it( 'should render second step with Add a link on-boarding content', () => {
		const wrapper = renderComponent( {
			open: true,
			initialStep: 2
		} );
		expect( wrapper.html() ).toMatchSnapshot();
	} );
	it( 'should render third step with Add a link on-boarding content', () => {
		const wrapper = renderComponent( {
			open: true,
			initialStep: 3
		} );
		expect( wrapper.html() ).toMatchSnapshot();
	} );
	it( 'should add a "learn more" link if an url is provided', () => {
		const wrapper = renderComponent( {
			open: true,
			initialStep: 2,
			learnMoreLink: 'https://www.wikipedia.org/'
		} );
		expect( wrapper.html() ).toMatchSnapshot();
	} );
} );
