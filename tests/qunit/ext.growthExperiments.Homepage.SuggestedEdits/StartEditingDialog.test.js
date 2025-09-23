'use strict';
const StartEditingDialog = require( 'ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.js' );
const HomepageModuleLogger = require( '../../../modules/ext.growthExperiments.Homepage.Logger/index.js' );
const rootStore = require( '../__mocks__/store.js' );

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.js', QUnit.newMwEnvironment( {
	config: {
		GEHomepageSuggestedEditsEnableTopics: true,
	},
} ) );

QUnit.test( 'should log topicmatchmode impressions', function ( assert ) {
	const logger = new HomepageModuleLogger( true, 'some-token' );
	this.sandbox.stub( logger, 'log' );

	const dialog = new StartEditingDialog( {
		useTaskTypeSelector: true,
		useTopicSelector: true,
		useTopicMatchMode: true,
		mode: 'some-mode',
		module: 'some-module',
	}, logger, rootStore );
	this.sandbox.spy( dialog, 'updateMatchCount' );

	const windowManager = new OO.ui.WindowManager( { modal: false } );
	windowManager.addWindows( [ dialog ] );

	assert.deepEqual( logger.log.getCall( 0 ).args, [
		'some-module',
		'some-mode',
		'se-topicmatchmode-impression',
	] );

	dialog.topicSelector.emit( 'toggleMatchMode', 'MODE' );

	assert.true( dialog.updateMatchCount.calledOnce );
	assert.deepEqual( logger.log.getCall( 1 ).args, [
		'some-module',
		'some-mode',
		'se-topicmatchmode-mode',
		{
			topicsMatchMode: 'MODE',
		},
	] );
} );
