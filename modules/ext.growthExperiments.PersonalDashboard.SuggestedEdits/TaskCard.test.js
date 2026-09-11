'use strict';

/*
 * CodexModule generates codex.js and icons.json at ResourceLoader time and
 * ext.personalDashboard.common comes from a sibling extension, so none of the
 * three exist on disk for jest to resolve.
 */
jest.mock( './codex.js', () => ( { CdxIcon: { render: () => null } } ), { virtual: true } );
jest.mock( './icons.json', () => ( { cdxIconLightbulb: 'lightbulb' } ), { virtual: true } );
jest.mock( 'ext.personalDashboard.common', () => ( {
	FeedCard: { props: [ 'url', 'thumbnail', 'forceThumbnail' ], render: () => null },
} ), { virtual: true } );

const { mount } = require( '@vue/test-utils' );
const TaskCard = require( './TaskCard.vue' );

const CLICK_ID = 'pageview-token-1';

const TASK = {
	pageId: 101,
	revisionId: 102,
	tasktype: 'link-recommendation',
	title: 'Dandelion',
	token: 'task-token-1',
};

/**
 * @param {Object} task
 * @return {Object} the mounted wrapper
 */
function mountCard( task ) {
	mw.config.get.mockImplementation(
		( key ) => ( key === 'wgPersonalDashboardPageviewToken' ? CLICK_ID : undefined ),
	);
	return mount( TaskCard, { props: { task, position: 3 } } );
}

/**
 * @return {Object} the page name and query parameters the card handed mw.util.getUrl
 */
function link() {
	const [ page, params ] = mw.util.getUrl.mock.calls[ 0 ];
	return { page, params };
}

describe( 'TaskCard', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		window.history.replaceState( {}, '', '/wiki/Special:PersonalDashboard' );
	} );

	it( 'routes through Special:Homepage so the click starts a session and counts', () => {
		mountCard( { ...TASK } );

		expect( link() ).toEqual( {
			page: 'Special:Homepage/newcomertask/101',
			params: {
				geclickid: CLICK_ID,
				getasktype: 'link-recommendation',
				genewcomertasktoken: 'task-token-1',
				gesuggestededit: 1,
			},
		} );
	} );

	it( 'falls back to the article when the task has no page id', () => {
		mountCard( { ...TASK, pageId: null } );

		expect( link().page ).toBe( 'Dandelion' );
		expect( link().params.gesuggestededit ).toBe( 1 );
	} );

	it( 'carries an enrollment override through to the article', () => {
		window.history.replaceState( {}, '', '/wiki/Special:PersonalDashboard?mpo=treatment' );
		mountCard( { ...TASK } );

		expect( link().params.mpo ).toBe( 'treatment' );
	} );

	it( 'leaves an mpo-less dashboard URL out of the link', () => {
		mountCard( { ...TASK } );

		expect( link().params ).not.toHaveProperty( 'mpo' );
	} );

	it( 'sends a remote-origin card to its own URL untouched', () => {
		const wrapper = mountCard( { ...TASK, url: 'https://en.wikipedia.org/wiki/Dandelion' } );

		expect( wrapper.vm.url ).toBe( 'https://en.wikipedia.org/wiki/Dandelion' );
		expect( mw.util.getUrl ).not.toHaveBeenCalled();
	} );
} );
