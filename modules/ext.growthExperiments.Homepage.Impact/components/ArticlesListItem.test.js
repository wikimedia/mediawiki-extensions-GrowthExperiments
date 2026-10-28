'use strict';

jest.mock( '../../vue-components/icons.json', () => ( {
	cdxIconClock: '',
} ), { virtual: true } );
const { mount } = require( '@vue/test-utils' );
const ArticlesListItem = require( './ArticlesListItem.vue' );

const renderComponent = ( instrumentation ) => mount( ArticlesListItem, {
	props: {
		index: 0,
		article: {
			href: '/wiki/Example',
			title: 'Example',
			image: { href: 'https://example.org/example.jpg' },
			views: {
				count: 12,
				href: '/pageviews/Example',
				entries: [ { date: new Date( '2022-12-14' ), views: 12 } ],
			},
		},
	},
	global: {
		provide: {
			RENDER_MODE: 'desktop',
			HOME_MODULE_INSTRUMENTATION: instrumentation,
		},
		mocks: {
			$filters: {
				convertNumber: jest.fn( ( x ) => `${ x }` ),
			},
		},
	},
} );

describe( 'ArticlesListItem', () => {
	it( 'sends a click event for the article link', () => {
		const instrumentation = { sendClick: jest.fn() };
		const wrapper = renderComponent( instrumentation );

		wrapper.find( '[data-link-id="impact-article-title"]' ).trigger( 'click' );

		expect( instrumentation.sendClick ).toHaveBeenCalledWith( 'module_impact_article_link' );
	} );

	it( 'sends a click event for the page views link', () => {
		const instrumentation = { sendClick: jest.fn() };
		const wrapper = renderComponent( instrumentation );

		wrapper.find( '[data-link-id="impact-pageviews"]' ).trigger( 'click' );

		expect( instrumentation.sendClick ).toHaveBeenCalledWith( 'module_impact_article_stat' );
	} );
} );
