'use strict';

const HomepageModuleLogger = require( '../../../modules/homepage/ext.growthExperiments.Homepage.Logger.js' );

QUnit.module( 'ext.growthExperiments.Homepage.Logger.js', QUnit.newMwEnvironment( {
	config: {
		'wgGEHomepageModuleState-mentor': 1,
		wgUserEditCount: 123,
		'wgGEHomepageModuleActionData-tutorial': { foo: 'bar' },
		'wgGEHomepageModuleState-tutorial': 'done',
		wgGEHomepageUserVariant: 'X'
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
	homepageModuleLogger.log( 'tutorial', 'desktop', 'impression', { foo: 'bar' } );
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
		module: 'tutorial',
		is_mobile: OO.ui.isMobile(),
		mode: 'desktop',
		homepage_pageview_token: 'blah'
		/* eslint-enable camelcase */
	} );
} );

QUnit.test( 'exclude start', function ( assert ) {
	const homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
	homepageModuleLogger.log( 'start', 'mode', 'impression' );
	assert.strictEqual( homepageModuleLogger.getEvents().length, 0 );
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
