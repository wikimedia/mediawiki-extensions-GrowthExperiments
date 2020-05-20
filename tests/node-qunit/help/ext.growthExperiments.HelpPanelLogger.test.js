var jsdom = require( 'jsdom' ),
	sinon = require( 'sinon' ),
	HelpPanelLogger = require( '../../../modules/help/ext.growthExperiments.HelpPanelLogger.js' ),
	sandbox,
	dom;

QUnit.module( 'HelpPanelLogger', {
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
		var helpPanelLogger = new HelpPanelLogger( false );
		helpPanelLogger.log();
		assert.strictEqual( global.mw.track.called, false );
		helpPanelLogger = new HelpPanelLogger( true );
		helpPanelLogger.log();
		assert.strictEqual( global.mw.track.called, true );
	} );
	QUnit.test( 'log', function ( assert ) {
		var helpPanelLogger = new HelpPanelLogger( true, {
			context: 'reading',
			editorInterface: 'visualeditor',
			sessionId: 'foo'
		} );
		// eslint-disable-next-line camelcase
		helpPanelLogger.log( 'impression', 'blah', { editor_interface: 'wikitext' } );
		assert.strictEqual( global.mw.track.getCall( 0 ).args[ 0 ], 'event.HelpPanel' );
		assert.deepEqual( global.mw.track.getCall( 0 ).args[ 1 ], {
			/* eslint-disable camelcase */
			action: 'impression',
			action_data: 'blah',
			user_id: 24,
			user_editcount: 42,
			context: 'reading',
			editor_interface: 'wikitext',
			is_suggested_task: false,
			is_mobile: false,
			page_id: 0,
			page_title: '',
			page_ns: 42,
			user_can_edit: 42,
			page_protection: '',
			session_token: 'bar',
			help_panel_session_id: 'foo'
			/* eslint-enable camelcase */
		} );
	} );

} );
