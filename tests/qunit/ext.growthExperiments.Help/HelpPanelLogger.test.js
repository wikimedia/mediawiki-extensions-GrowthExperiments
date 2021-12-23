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
	let helpPanelLogger = new HelpPanelLogger( false );
	helpPanelLogger.log();
	let events = helpPanelLogger.getEvents();
	assert.strictEqual( events.length, 0 );
	helpPanelLogger = new HelpPanelLogger( true );
	helpPanelLogger.log();
	events = helpPanelLogger.getEvents();
	assert.strictEqual( events.length, 1 );
} );

QUnit.test( 'log', function ( assert ) {
	const helpPanelLogger = new HelpPanelLogger( true, {
		context: 'reading',
		previousEditorInterface: 'visualeditor',
		sessionId: 'foo'
	} );

	// eslint-disable-next-line camelcase
	helpPanelLogger.log( 'impression', 'blah', { editor_interface: 'wikitext' } );

	const events = helpPanelLogger.getEvents();
	delete events[ 0 ].session_token;
	assert.deepEqual( events[ 0 ], {
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
		page_ns: 0,
		user_can_edit: true,
		user_editcount: 42,
		page_protection: ''
		/* eslint-enable camelcase */
	} );
} );
