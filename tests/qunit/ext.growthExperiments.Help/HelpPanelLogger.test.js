'use strict';

const HelpPanelLogger = require( '../../../modules/ext.growthExperiments.Help/HelpPanelLogger.js' );

QUnit.module( 'ext.growthExperiments.Help/HelpPanelLogger.js', QUnit.newMwEnvironment( {
	config: {
		wgCanonicalSpecialPageName: false,
		wgUserEditCount: 42,
		wgNamespaceNumber: 0,
		wgIsProbablyEditable: true,
		wgUserId: 1
	}
} ) );

QUnit.test( 'disabled/enabled', function ( assert ) {
	this.sandbox.spy( mw, 'track' );
	let helpPanelLogger = new HelpPanelLogger( false );
	helpPanelLogger.log();
	assert.strictEqual( mw.track.notCalled, true );

	helpPanelLogger = new HelpPanelLogger( true );
	helpPanelLogger.log();
	assert.strictEqual( mw.track.calledOnce, true );
} );

QUnit.test( 'log', function ( assert ) {
	this.sandbox.spy( mw, 'track' );
	this.sandbox.stub( mw.user, 'sessionId' ).returns( '1234' );

	const helpPanelLogger = new HelpPanelLogger( true, {
		context: 'reading',
		previousEditorInterface: 'visualeditor',
		sessionId: 'foo'
	} );

	// eslint-disable-next-line camelcase
	helpPanelLogger.log( 'impression', 'blah', { editor_interface: 'wikitext' } );

	assert.strictEqual( mw.track.calledOnce, true );
	assert.strictEqual( mw.track.firstCall.args[ 0 ], 'event.HelpPanel' );

	assert.deepEqual( mw.track.firstCall.args[ 1 ], {
		/* eslint-disable camelcase */
		action: 'impression',
		action_data: 'blah',
		user_id: mw.user.getId(),
		context: 'reading',
		editor_interface: 'wikitext',
		help_panel_session_id: 'foo',
		is_suggested_task: false,
		is_mobile: OO.ui.isMobile(),
		page_id: 0,
		page_title: '',
		session_token: '1234',
		page_ns: 0,
		user_can_edit: true,
		user_editcount: 42,
		page_protection: ''
		/* eslint-enable camelcase */
	} );
} );
