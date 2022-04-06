'use strict';
const StartEditingDialog = require( '../../../modules/ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.js' );
const GrowthTasksApi = require( '../../../modules/ext.growthExperiments.Homepage.SuggestedEdits/GrowthTasksApi.js' );
const HomepageModuleLogger = require( '../../../modules/ext.growthExperiments.Homepage.Logger/index.js' );

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.js', QUnit.newMwEnvironment( {
	config: {
		GEHomepageSuggestedEditsEnableTopics: true
	}
} ) );

QUnit.test( 'should log topicmatchmode impressions', function ( assert ) {
	const api = new GrowthTasksApi( {
		taskTypes: {
			copyedit: {
				id: 'copyedit'
			}
		}
	} );
	const logger = new HomepageModuleLogger( true, 'some-token' );
	this.sandbox.stub( logger, 'log' );

	const dialog = new StartEditingDialog( {
		useTaskTypeSelector: true,
		useTopicSelector: true,
		useTopicMatchMode: true,
		mode: 'some-mode',
		module: 'some-module'
	}, logger, api );
	this.sandbox.spy( dialog, 'updateMatchCount' );

	const windowManager = new OO.ui.WindowManager( { modal: false } );
	windowManager.addWindows( [ dialog ] );

	assert.deepEqual( logger.log.getCall( 0 ).args, [
		'some-module',
		'some-mode',
		'se-topicmatchmode-impression'
	] );

	dialog.topicSelector.emit( 'toggleMatchMode', 'MODE' );

	assert.true( dialog.updateMatchCount.calledOnce );
	assert.deepEqual( logger.log.getCall( 1 ).args, [
		'some-module',
		'some-mode',
		'se-topicmatchmode-mode',
		{
			topicsMatchMode: 'MODE'
		}
	] );
} );
