'use strict';

const HomepageModuleLogger = require( '../../../modules/ext.growthExperiments.Homepage.Logger/index.js' );

QUnit.module( 'ext.growthExperiments.Homepage.Logger/index.js', QUnit.newMwEnvironment( {
	config: {
		'wgGEHomepageModuleState-mentor': 1,
		wgUserEditCount: 123,
		'wgGEHomepageModuleActionData-foo': { foo: 'bar' },
		'wgGEHomepageModuleState-foo': 'done',
		wgGEUserVariants: [ 'X', 'Y' ]
	},
	beforeEach: function () {
		this.sandbox.stub( mw.user.options, 'get' );
		mw.user.options.get.withArgs( 'growthexperiments-homepage-variant' ).returns( 'X' );
	}
} ) );

QUnit.test( 'disabled/enabled', function ( assert ) {
	let homepageModuleLogger = new HomepageModuleLogger( false, 'blah' );
	homepageModuleLogger.log();
	let events = homepageModuleLogger.getEvents();
	assert.strictEqual( events.length, 0 );
	homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
	homepageModuleLogger.log();
	events = homepageModuleLogger.getEvents();
	assert.strictEqual( events.length, 1 );
} );

QUnit.test( 'log', function ( assert ) {
	const homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
	homepageModuleLogger.log( 'foo', 'desktop', 'impression', { foo: 'bar' } );
	const events = homepageModuleLogger.getEvents();
	assert.strictEqual( events.length, 1 );
	assert.deepEqual( events[ 0 ], {
		/* eslint-disable camelcase */
		action: 'impression',
		action_data: 'foo=bar',
		state: 'done',
		user_id: mw.user.getId(),
		user_editcount: 123,
		user_variant: 'X',
		module: 'foo',
		is_mobile: OO.ui.isMobile(),
		mode: 'desktop',
		homepage_pageview_token: 'blah'
		/* eslint-enable camelcase */
	} );
} );

QUnit.test( 'do not include state in event if empty', function ( assert ) {
	const homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
	mw.config.set( 'wgGEHomepageModuleState-mentor', '' );
	homepageModuleLogger.log( 'mentor', 'desktop', 'impression' );
	const events = homepageModuleLogger.getEvents();
	assert.strictEqual( events.length, 1 );
	assert.deepEqual( events[ 0 ], {
		/* eslint-disable camelcase */
		action: 'impression',
		action_data: '',
		user_id: mw.user.getId(),
		user_editcount: 123,
		user_variant: 'X',
		module: 'mentor',
		is_mobile: OO.ui.isMobile(),
		mode: 'desktop',
		homepage_pageview_token: 'blah'
		/* eslint-enable camelcase */
	} );
} );
