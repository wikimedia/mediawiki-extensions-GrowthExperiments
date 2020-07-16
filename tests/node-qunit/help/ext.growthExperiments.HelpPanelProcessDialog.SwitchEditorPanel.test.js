'use strict';
const jsdom = require( 'jsdom' ),
	sinon = require( 'sinon' ),
	pathToSwitchEditorPanel = '../../../modules/help/ext.growthExperiments.HelpPanelProcessDialog.SwitchEditorPanel.js';
let SwitchEditorPanel,
	sandbox,
	dom;

QUnit.module( 'SwitchEditorPanel', {
	beforeEach: function () {
		sandbox = sinon.createSandbox();
		dom = new jsdom.JSDOM( '<!doctype html><html><body></body></html>' );
		global.window = dom.window;
		global.document = global.window.document;
		global.jQuery = global.$ = window.jQuery = window.$ = require( 'jquery' );
		global.OO = require( 'oojs' );
		global.mw.user = {
			options: {
				get: sinon.stub()
			}
		};
		global.mw.libs = {
			ve: sinon.stub()
		};
		require( 'oojs-ui' );
	},

	afterEach: function () {
		delete require.cache[ require.resolve( 'jquery' ) ];
		sandbox.reset();
	}
}, function () {

	QUnit.test( 'constructor', function ( assert ) {
		SwitchEditorPanel = require( pathToSwitchEditorPanel );
		global.mw.message = sinon.stub().returns( {
			text: sinon.stub().returns( 'Stub text' ),
			parse: sinon.stub().returns( 'Stub parsed text' )
		} );
		global.mw.user.options.get.returns( 0 );

		const switchEditorPanel = new SwitchEditorPanel( {
			preferredEditor: 'visualeditor'
		} );
		assert.ok( true );
		assert.strictEqual( switchEditorPanel.preferredEditor, 'visualeditor' );
	} );

} );
