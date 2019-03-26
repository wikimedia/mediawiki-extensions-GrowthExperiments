var jsdom = require( 'jsdom' ),
	sinon = require( 'sinon' ),
	HomepageModuleLogger = require( '../../../modules/homepage/ext.growthExperiments.Homepage.Logger.js' ),
	sandbox,
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
		global.mw.config.get = sinon.stub().returns( 42 );
		global.mw.user = {};
		global.mw.user.generateRandomSessionId = sinon.stub().returns( 'foo' );
		global.mw.user.getId = sinon.stub().returns( 24 );
		global.mw.user.sessionId = sinon.stub().returns( 'bar' );
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
		var homepageModuleLogger = new HomepageModuleLogger( false, 'blah' );
		homepageModuleLogger.log();
		assert.strictEqual( global.mw.track.called, false );
		homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
		homepageModuleLogger.log();
		assert.strictEqual( global.mw.track.called, true );
	} );
	QUnit.test( 'log', function ( assert ) {
		var homepageModuleLogger = new HomepageModuleLogger( true, 'blah' );
		homepageModuleLogger.log( 'tutorial', 'hover-in', { foo: 'bar' } );
		assert.strictEqual( global.mw.track.getCall( 0 ).args[ 0 ], 'event.HomepageModule' );
		assert.deepEqual( global.mw.track.getCall( 0 ).args[ 1 ], {
			/* eslint-disable camelcase */
			action: 'hover-in',
			action_data: 'foo=bar',
			user_id: 24,
			user_editcount: 42,
			module: 'tutorial',
			is_mobile: false,
			homepage_pageview_token: 'blah'
			/* eslint-enable camelcase */
		} );
	} );

} );
