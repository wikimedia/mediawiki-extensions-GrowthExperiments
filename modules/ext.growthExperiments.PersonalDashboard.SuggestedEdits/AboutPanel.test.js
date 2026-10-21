'use strict';

/*
 * ext.personalDashboard.common comes from a sibling extension and config.json
 * is the module's config package file, so neither exists on disk for jest to
 * resolve.
 */
jest.mock( 'ext.personalDashboard.common', () => {
	const { h } = require( 'vue' );
	return {
		// A render function, not a template: jest runs the runtime-only Vue build.
		ModulePanel: {
			props: [ 'open', 'anchor', 'isNarrow', 'title' ],
			setup: ( props, { slots } ) => () => h(
				'div', { class: 'module-panel' }, slots.default(),
			),
		},
	};
}, { virtual: true } );
jest.mock( './config.json', () => ( {
	GEHomepageSuggestedEditsRecommendationsUrl: 'https://example.org/Recommendations',
} ), { virtual: true } );

const { mount } = require( '@vue/test-utils' );
const AboutPanel = require( './AboutPanel.vue' );

describe( 'AboutPanel', () => {
	it( 'explains the module in three paragraphs', () => {
		const wrapper = mount( AboutPanel, { props: { open: true } } );

		const paragraphs = wrapper.findAll( '.module-panel p' );
		expect( paragraphs ).toHaveLength( 3 );
		expect( paragraphs[ 0 ].text() )
			.toBe( 'growthexperiments-home-suggestededits-about-intro' );
		expect( paragraphs[ 1 ].text() )
			.toBe( 'growthexperiments-home-suggestededits-about-purpose' );
	} );

	it( 'gives the recommendations link the URL the wiki configures', () => {
		const wrapper = mount( AboutPanel, { props: { open: true } } );

		// jest.setup.js renders the i18n-html directive as "key (params)".
		expect( wrapper.findAll( '.module-panel p' )[ 2 ].html() ).toContain(
			'growthexperiments-home-suggestededits-about-recommendations ' +
			'(https://example.org/Recommendations)',
		);
	} );

	it( 'keeps the external link icon off the recommendations link', () => {
		const wrapper = mount( AboutPanel, { props: { open: true } } );

		// Minerva adds the icon to `a.external`. `plainlinks` removes it.
		expect( wrapper.findAll( '.module-panel p' )[ 2 ].classes() )
			.toContain( 'plainlinks' );
	} );

	it( 'reports itself closed so the menu above can close it', async () => {
		const wrapper = mount( AboutPanel, { props: { open: true } } );

		wrapper.findComponent( { name: 'ModulePanel' } ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'update:open' ) ).toStrictEqual( [ [ false ] ] );
	} );
} );
