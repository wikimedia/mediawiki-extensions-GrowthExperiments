'use strict';

jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );

const { mount } = require( '@vue/test-utils' );
const NoMatchNotice = require( './NoMatchNotice.vue' );

describe( 'NoMatchNotice', () => {
	it( 'shows the message and the two buttons', () => {
		const wrapper = mount( NoMatchNotice );

		expect( wrapper.text() ).toContain( 'growthexperiments-home-suggestededits-nomatch-notice' );
		const buttons = wrapper.findAll( '.growthexperiments-nomatch-notice__buttons button' );
		expect( buttons.map( ( button ) => button.text() ) ).toEqual( [
			'growthexperiments-home-suggestededits-nomatch-notice-interests-button',
			'growthexperiments-home-suggestededits-nomatch-notice-tasktypes-button',
		] );
	} );

	it( 'emits an event for each button', async () => {
		const wrapper = mount( NoMatchNotice );
		const buttons = wrapper.findAll( '.growthexperiments-nomatch-notice__buttons button' );

		await buttons[ 0 ].trigger( 'click' );
		await buttons[ 1 ].trigger( 'click' );

		expect( wrapper.emitted( 'select-interests' ) ).toHaveLength( 1 );
		expect( wrapper.emitted( 'select-task-types' ) ).toHaveLength( 1 );
	} );

	it( 'emits dismiss when the user closes the message', async () => {
		const wrapper = mount( NoMatchNotice );

		await wrapper.find( '.cdx-message__dismiss-button' ).trigger( 'click' );

		expect( wrapper.emitted( 'dismiss' ) ).toHaveLength( 1 );
	} );
} );
