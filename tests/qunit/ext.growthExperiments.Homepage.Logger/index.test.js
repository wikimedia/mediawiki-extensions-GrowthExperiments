'use strict';

const HomepageModuleLogger = require( '../../../modules/ext.growthExperiments.Homepage.Logger/index.js' );

QUnit.module( 'ext.growthExperiments.Homepage.Logger/index.js', QUnit.newMwEnvironment( {
	config: {
		'wgGEHomepageModuleState-mentor': 1,
		wgUserEditCount: 123,
		'wgGEHomepageModuleActionData-foo': { foo: 'bar' },
		'wgGEHomepageModuleState-foo': 'done',
		wgGEUserVariants: [ 'X', 'Y' ],
	},
	beforeEach: function () {
		this.sandbox.stub( mw.user.options, 'get' );
		mw.user.options.get.withArgs( 'growthexperiments-homepage-variant' ).returns( 'X' );
	},
} ) );

QUnit.test( 'log', function ( assert ) {
	this.sandbox.spy( mw, 'track' );
	const homepageModuleLogger = new HomepageModuleLogger( 'blah' );
	homepageModuleLogger.log( 'foo', 'desktop', 'impression', { foo: 'bar' } );

	assert.strictEqual( mw.track.calledOnce, true );
	assert.strictEqual( mw.track.firstCall.args[ 0 ], 'event.HomepageModule' );
	assert.deepEqual( mw.track.firstCall.args[ 1 ], {
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
		homepage_pageview_token: 'blah',
		/* eslint-enable camelcase */
	} );
} );

QUnit.test( 'do not include state in event if empty', function ( assert ) {
	this.sandbox.spy( mw, 'track' );
	const homepageModuleLogger = new HomepageModuleLogger( 'blah' );
	mw.config.set( 'wgGEHomepageModuleState-mentor', '' );
	homepageModuleLogger.log( 'mentor', 'desktop', 'impression' );

	assert.strictEqual( mw.track.calledOnce, true );
	assert.strictEqual( mw.track.firstCall.args[ 0 ], 'event.HomepageModule' );
	assert.deepEqual( mw.track.firstCall.args[ 1 ], {
		/* eslint-disable camelcase */
		action: 'impression',
		action_data: '',
		user_id: mw.user.getId(),
		user_editcount: 123,
		user_variant: 'X',
		module: 'mentor',
		is_mobile: OO.ui.isMobile(),
		mode: 'desktop',
		homepage_pageview_token: 'blah',
		/* eslint-enable camelcase */
	} );
} );
