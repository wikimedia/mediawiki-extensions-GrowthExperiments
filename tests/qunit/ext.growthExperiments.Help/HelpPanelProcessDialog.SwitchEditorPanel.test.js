'use strict';
const pathToSwitchEditorPanel = '../../../modules/ext.growthExperiments.Help/HelpPanelProcessDialog.SwitchEditorPanel.js';
let SwitchEditorPanel;

QUnit.module( 'ext.growthExperiments.Help/HelpPanelProcessDialog.SwitchEditorPanel.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor', function ( assert ) {
	SwitchEditorPanel = require( pathToSwitchEditorPanel );

	const switchEditorPanel = new SwitchEditorPanel( {
		preferredEditor: 'visualeditor'
	} );
	assert.ok( true );
	assert.strictEqual( switchEditorPanel.preferredEditor, 'visualeditor' );
} );
