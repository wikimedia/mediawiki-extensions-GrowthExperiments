'use strict';

const jsdom = require( 'jsdom' ),
	sinon = require( 'sinon' ),
	HomepageModuleLogger = require( '../../../modules/homepage/ext.growthExperiments.Homepage.Logger.js' );
let sandbox,
	dom;

QUnit.module( 'HomepageLogger', {
	beforeEach: function () {
		sandbox = sinon.createSandbox();
		dom = new jsdom.JSDOM( '<!doctype html><html><body></body></html>' );
		global.window = dom.window;
		global.document = global.window.document;
		global.jQuery = global.$ = window.jQuery = window.$ = require( 'jquery' );
		global.OO = require( 'oojs' );

		// Both OOUI and the WMF theme need to be loaded into scope via require();
		// properties are automatically added to OO namespace.
		require( 'oojs-ui' );
		require( 'oojs-ui/dist/oojs-ui-wikimediaui.js' );

		global.mw = {};
		global.mw.config = {};
		const configGet = sinon.stub();
		configGet.withArgs( 'wgUserEditCount' ).returns( 123 );
		configGet.withArgs( 'wgGEHomepageModuleActionData-tutorial' ).returns( { foo: 'bar' } );
		configGet.withArgs( 'wgGEHomepageModuleState-tutorial' ).returns( 'done' );
		configGet.withArgs( 'wgGEHomepageUserVariant' ).returns( 'X' );
		global.mw.config.get = configGet;
		global.mw.user = {};
		global.mw.user.generateRandomSessionId = sinon.stub().returns( 'foo' );
		global.mw.user.getId = sinon.stub().returns( 24 );
		global.mw.user.sessionId = sinon.stub().returns( 'bar' );
		global.mw.user.options = {};
		global.mw.user.options.get = sinon.stub()
			.withArgs( 'growthexperiments-homepage-variant' )
			.returns( 'X' );
		global.mw.track = sinon.stub();
		global.mw.Uri = sinon.stub().returns( {
			query: sinon.stub()
		} );
	},

	afterEach: function () {
		delete require.cache[ require.resolve( 'jquery' ) ];
		sandbox.reset();
	}
}, function () {
	QUnit.test( 'disabled/enabled', function ( assert ) {
		let homepageModuleLogger = new HomepageModuleLogger( false, 'blah' );
		homepageModuleLogger.log();
		assert.strictEqual( global.mw.track.called, false );
		homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
		homepageModuleLogger.log();
		assert.strictEqual( global.mw.track.called, true );
	} );
	QUnit.test( 'log', function ( assert ) {
		const homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
		homepageModuleLogger.log( 'tutorial', 'desktop', 'impression', { foo: 'bar' } );
		assert.strictEqual( global.mw.track.getCall( 0 ).args[ 0 ], 'event.HomepageModule' );
		assert.deepEqual( global.mw.track.getCall( 0 ).args[ 1 ], {
			/* eslint-disable camelcase */
			action: 'impression',
			action_data: 'foo=bar',
			state: 'done',
			user_id: 24,
			user_editcount: 123,
			user_variant: 'X',
			module: 'tutorial',
			is_mobile: false,
			mode: 'desktop',
			homepage_pageview_token: 'blah'
			/* eslint-enable camelcase */
		} );
	} );

	QUnit.test( 'exclude start', function ( assert ) {
		const homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
		homepageModuleLogger.log( 'start', 'mode', 'impression' );
		assert.strictEqual( global.mw.track.called, false );
	} );

	QUnit.test( 'do not include state in event if empty', function ( assert ) {
		const homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
		global.mw.config.get.withArgs( 'wgGEHomepageModuleState-mentor' ).returns( '' );
		homepageModuleLogger.log( 'mentor', 'desktop', 'impression' );
		assert.strictEqual( global.mw.track.getCall( 0 ).args[ 0 ], 'event.HomepageModule' );
		assert.deepEqual( global.mw.track.getCall( 0 ).args[ 1 ], {
			/* eslint-disable camelcase */
			action: 'impression',
			action_data: '',
			user_id: 24,
			user_editcount: 123,
			user_variant: 'X',
			module: 'mentor',
			is_mobile: false,
			mode: 'desktop',
			homepage_pageview_token: 'blah'
			/* eslint-enable camelcase */
		} );
	} );
} );
