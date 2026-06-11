'use strict';
const StartEditingDialog = require( 'ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.js' );
const rootStore = require( '../__mocks__/store.js' );

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.js', QUnit.newMwEnvironment( {
	config: {
		GEHomepageSuggestedEditsEnableTopics: true,
	},
} ) );

QUnit.test( 'updateMatchCount is triggered when the topic selector toggles match mode', function ( assert ) {
	const dialog = new StartEditingDialog( {
		useTaskTypeSelector: true,
		useTopicSelector: true,
		useTopicMatchMode: true,
		mode: 'some-mode',
		module: 'some-module',
	}, rootStore );
	this.sandbox.spy( dialog, 'updateMatchCount' );

	const windowManager = new OO.ui.WindowManager( { modal: false } );
	windowManager.addWindows( [ dialog ] );

	dialog.topicSelector.emit( 'toggleMatchMode', 'MODE' );

	assert.true( dialog.updateMatchCount.calledOnce );
} );
